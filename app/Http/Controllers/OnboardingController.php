<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function dismiss(Request $request, OnboardingService $onboarding): RedirectResponse
    {
        $onboarding->dismiss($request->user());

        return back()->with('success', 'Got it — the checklist is hidden. You can find shortcuts any time with ?.');
    }
}
