<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The caller's getting-started checklist — what the web dashboards show. */
class OnboardingController extends ApiController
{
    public function __construct(private readonly OnboardingService $onboarding) {}

    public function show(Request $request): JsonResponse
    {
        return $this->ok($this->payload($request));
    }

    public function dismiss(Request $request): JsonResponse
    {
        $this->onboarding->dismiss($request->user());

        return $this->ok($this->payload($request), 'Checklist dismissed.');
    }

    /** @return array{visible: bool, steps: list<array<string, mixed>>} */
    private function payload(Request $request): array
    {
        $user = $request->user();
        $steps = $this->onboarding->checklist($user);

        return [
            'visible' => $this->onboarding->isVisible($user, $steps),
            'steps' => $steps->map(fn (array $step) => [
                'key' => $step['key'],
                'label' => $step['label'],
                'description' => $step['description'],
                'done' => $step['done'],
            ])->all(),
        ];
    }
}
