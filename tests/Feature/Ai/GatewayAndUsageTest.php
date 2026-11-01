<?php

namespace Tests\Feature\Ai;

use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\AiFeatures;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiTransport;

class GatewayAndUsageTest extends AiTestCase
{
    private function callClaude(): array
    {
        return app(AiGateway::class)->json('catch_up', 'system', 'hello', ['type' => 'object'], User::factory()->create());
    }

    public function test_a_successful_call_is_logged_with_tokens_and_model(): void
    {
        $this->claude->respond(['ok' => true]);

        $this->assertSame(['ok' => true], $this->callClaude());

        $log = AiUsageLog::sole();
        $this->assertSame('catch_up', $log->feature);
        $this->assertSame('ok', $log->status);
        $this->assertSame(1200, $log->input_tokens);
        $this->assertSame(config('ai.features.catch_up.model'), $log->model);
        $this->assertSame('low', $this->claude->requests[0]['effort']);
    }

    public function test_refusal_truncation_and_bad_json_become_logged_failures(): void
    {
        $this->claude->respond(['x' => 1], 'refusal')->respond(['x' => 1], 'max_tokens')->respond('not json');

        foreach (['refused', 'truncated', 'invalid'] as $status) {
            try {
                $this->callClaude();
                $this->fail('Expected an AiException');
            } catch (AiException) {
                $this->assertSame($status, AiUsageLog::latest('id')->first()->status);
            }
        }
    }

    public function test_a_switched_off_feature_never_calls_claude(): void
    {
        app(AiFeatures::class)->set('catch_up', false, User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->expectException(AiException::class);
        try {
            $this->callClaude();
        } finally {
            $this->assertSame([], $this->claude->requests);
        }
    }

    public function test_without_an_api_key_the_feature_answers_not_configured(): void
    {
        config(['services.anthropic.key' => null]);
        $this->app->forgetInstance(AiTransport::class);
        $this->app->offsetUnset(AiTransport::class);
        $this->app->bind(AiTransport::class, fn () => null);

        $this->expectExceptionMessage('isn’t configured');
        $this->callClaude();
    }

    public function test_usage_page_is_admin_only_and_toggles_features(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);
        $this->claude->respond(['ok' => true]);
        $this->callClaude();

        $this->actingAs($admin)->get(route('ai.usage'))->assertOk()
            ->assertSee('Catch me up')->assertSee('Claude calls');

        $this->actingAs($admin)->post(route('ai.features.toggle', 'catch_up'), ['enabled' => 0])->assertRedirect();
        $this->assertFalse(app(AiFeatures::class)->enabled('catch_up'));

        foreach ([User::ROLE_CTO, User::ROLE_DEVELOPER, User::ROLE_VIEWER] as $role) {
            $this->actingAs($this->user($role))->get(route('ai.usage'))->assertForbidden();
        }
    }

    public function test_navigation_shows_ask_to_everyone_and_usage_to_admins(): void
    {
        $this->actingAs($this->user(User::ROLE_DEVELOPER, $this->team))->get(route('dashboard'))
            ->assertSee(route('ai.ask.page'))->assertDontSee(route('ai.usage'));
        $this->actingAs($this->user(User::ROLE_ADMIN))->get(route('dashboard'))->assertSee(route('ai.usage'));
    }
}
