<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the `api-authentication` capability spec.
 */
class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $attributes = []): User
    {
        return User::factory()->create($attributes + [
            'role' => User::ROLE_DEVELOPER,
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * Drop the resolved guard between requests.
     *
     * A test process reuses one application instance, so Sanctum's RequestGuard
     * keeps the user it resolved on the previous call. Production has no such
     * carry-over — every request boots its own container — so this is a harness
     * artifact, and clearing it is what makes "the token stopped working"
     * observable in a test at all.
     */
    private function forgetResolvedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_valid_credentials_issue_a_token(): void
    {
        $user = $this->user(['email' => 'dev@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'dev@example.com',
            'password' => 'password',
            'device_name' => 'Desktop — macOS',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token', 'user' => ['permissions']]]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_invalid_credentials_are_rejected_without_revealing_the_account(): void
    {
        $this->user(['email' => 'dev@example.com']);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'dev@example.com',
            'password' => 'not-the-password',
            'device_name' => 'Desktop',
        ]);

        $unknownEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
            'device_name' => 'Desktop',
        ]);

        $wrongPassword->assertStatus(422);
        $unknownEmail->assertStatus(422);

        // The two failures must be indistinguishable, or the endpoint becomes
        // an account-enumeration oracle.
        $this->assertSame(
            $wrongPassword->json('errors.email'),
            $unknownEmail->json('errors.email')
        );
    }

    public function test_device_name_is_required(): void
    {
        $this->user(['email' => 'dev@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'dev@example.com',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('device_name');
    }

    public function test_deactivated_account_cannot_log_in(): void
    {
        $this->user(['email' => 'gone@example.com', 'deactivated_at' => now()]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'gone@example.com',
            'password' => 'password',
            'device_name' => 'Desktop',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertSame(0, User::withTrashed()->where('email', 'gone@example.com')->first()->tokens()->count());
    }

    public function test_protected_endpoints_require_a_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_deactivation_revokes_access_on_the_next_request(): void
    {
        $user = $this->user();
        $token = $user->createToken('Desktop')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $user->update(['deactivated_at' => now()]);
        $this->forgetResolvedUser();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();

        // The token is revoked outright, so the client is signed out rather
        // than left retrying a credential that will never work again.
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_me_returns_effective_permissions(): void
    {
        $lead = $this->user(['role' => User::ROLE_TEAM_LEAD]);
        Sanctum::actingAs($lead);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.role', User::ROLE_TEAM_LEAD)
            ->assertJsonPath('data.permissions.manage_releases', true)
            ->assertJsonPath('data.permissions.manage_users', true)
            ->assertJsonPath('data.permissions.has_limited_access', false)
            // A team lead evaluates but does not reconfigure the catalog.
            ->assertJsonPath('data.permissions.manage_competencies', false);
    }

    public function test_developer_permissions_reflect_limited_access(): void
    {
        Sanctum::actingAs($this->user(['role' => User::ROLE_DEVELOPER]));

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.permissions.has_limited_access', true)
            ->assertJsonPath('data.permissions.manage_releases', false)
            ->assertJsonPath('data.permissions.manage_users', false);
    }

    public function test_user_payload_never_contains_credentials(): void
    {
        Sanctum::actingAs($this->user());

        $response = $this->getJson('/api/v1/me')->assertOk();

        $this->assertArrayNotHasKey('password', $response->json('data'));
        $this->assertArrayNotHasKey('remember_token', $response->json('data'));
    }

    public function test_logout_revokes_only_the_current_device(): void
    {
        $user = $this->user();
        $desktop = $user->createToken('Desktop')->plainTextToken;
        $user->createToken('Laptop');

        $this->withToken($desktop)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(1, $user->fresh()->tokens()->count());
        $this->assertSame('Laptop', $user->fresh()->tokens()->first()->name);

        $this->forgetResolvedUser();
        $this->withToken($desktop)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_logout_all_signs_out_every_device(): void
    {
        $user = $this->user();
        $desktop = $user->createToken('Desktop')->plainTextToken;
        $user->createToken('Laptop');

        $this->withToken($desktop)->postJson('/api/v1/auth/logout-all')->assertOk();

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_user_lists_only_their_own_devices(): void
    {
        $user = $this->user();
        $other = $this->user();
        $token = $user->createToken('Desktop')->plainTextToken;
        $other->createToken('Someone else’s laptop');

        $this->withToken($token)->getJson('/api/v1/auth/tokens')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_name', 'Desktop')
            ->assertJsonPath('data.0.is_current', true);
    }

    public function test_a_user_cannot_revoke_another_users_device(): void
    {
        $user = $this->user();
        $other = $this->user();
        $token = $user->createToken('Desktop')->plainTextToken;
        $victim = $other->createToken('Victim');

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/tokens/'.$victim->accessToken->id)
            ->assertNotFound();

        $this->assertSame(1, $other->fresh()->tokens()->count());
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-1234',
            'password_confirmation' => 'new-password-1234',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');
    }

    public function test_password_change_signs_out_other_devices_but_not_this_one(): void
    {
        $user = $this->user();
        $desktop = $user->createToken('Desktop')->plainTextToken;
        $user->createToken('Laptop');

        $this->withToken($desktop)->putJson('/api/v1/me/password', [
            'current_password' => 'password',
            'password' => 'new-password-1234',
            'password_confirmation' => 'new-password-1234',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-1234', $user->fresh()->password));

        // The device that made the change stays in; the others are cut.
        $this->assertSame(1, $user->fresh()->tokens()->count());
        $this->withToken($desktop)->getJson('/api/v1/me')->assertOk();
    }

    public function test_profile_update_changes_name_and_email(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me', [
            'name' => 'Renamed Person',
            'email' => 'renamed@example.com',
        ])->assertOk()->assertJsonPath('data.name', 'Renamed Person');

        $this->assertSame('renamed@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_there_is_no_public_registration_endpoint(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'password',
        ])->assertNotFound();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        $this->user(['email' => 'dev@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'dev@example.com',
                'password' => 'wrong',
                'device_name' => 'Desktop',
            ])->assertStatus(422);
        }

        // The sixth attempt is refused even with the *correct* password, and
        // the response says how long to wait rather than just saying no.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'dev@example.com',
            'password' => 'password',
            'device_name' => 'Desktop',
        ])->assertStatus(429);

        $this->assertNotNull($response->headers->get('Retry-After'));
    }
}
