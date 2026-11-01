<?php

namespace Tests\Feature\Ai;

use App\Models\AiUsageLog;
use App\Models\User;
use App\Services\Ai\AiException;
use App\Services\Ai\AiFeatures;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiTransport;
use App\Services\Calendar\EventExtractor;

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

    // -- Model choice ------------------------------------------------------------

    public function test_every_feature_defaults_to_sonnet(): void
    {
        foreach (app(AiFeatures::class)->all() as $key => $f) {
            $this->assertSame('claude-sonnet-5-5', $f['model'], $key);
        }
    }

    public function test_an_admin_changes_a_features_model_and_the_next_call_uses_it(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)->put(route('ai.features.configure', 'catch_up'), ['model' => 'claude-haiku-5-5', 'effort' => 'medium'])
            ->assertRedirect()->assertSessionHas('success');

        $this->claude->respond(['ok' => true]);
        $this->callClaude();

        $this->assertSame('claude-haiku-5-5', $this->claude->requests[0]['model']);
        $this->assertSame('medium', $this->claude->requests[0]['effort']);
        $this->assertSame('claude-haiku-5-5', AiUsageLog::sole()->model);
    }

    public function test_use_for_all_sets_every_feature_and_skips_unsupported_ones(): void
    {
        $admin = $this->user(User::ROLE_ADMIN);

        $this->actingAs($admin)->put(route('ai.features.configure-all'), ['model' => 'claude-haiku-5-5'])->assertRedirect();

        $all = app(AiFeatures::class)->all();
        $this->assertSame('claude-haiku-5-5', $all['release_notes']['model']);
        // KPI reports and calendar import always send the refusal fallback, which Haiku lacks.
        $this->assertSame('claude-sonnet-5-5', $all['kpi_review']['model']);
        $this->assertSame('claude-sonnet-5-5', $all['calendar_import']['model']);

        // Back to the defaults.
        $this->actingAs($admin)->put(route('ai.features.configure-all'), ['model' => ''])->assertRedirect();
        $this->assertSame('claude-sonnet-5-5', app(AiFeatures::class)->get('release_notes')['model']);
    }

    public function test_model_choices_are_validated_and_admin_only(): void
    {
        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->put(route('ai.features.configure', 'catch_up'), ['model' => 'gpt-4'])->assertSessionHasErrors('model');
        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->put(route('ai.features.configure', 'kpi_review'), ['model' => 'claude-haiku-5-5'])->assertSessionHasErrors('model');
        $this->actingAs($this->user(User::ROLE_CTO))
            ->put(route('ai.features.configure', 'catch_up'), ['model' => 'claude-opus-5-5'])->assertForbidden();
    }

    public function test_kpi_and_calendar_import_pick_up_the_chosen_model(): void
    {
        app(AiFeatures::class)->configure('calendar_import', 'claude-opus-5-5', 'high', $this->user(User::ROLE_ADMIN));

        $extractor = app(EventExtractor::class);
        $model = (fn () => $this->model)->call($extractor);

        $this->assertSame('claude-opus-5-5', $model);
    }
}
