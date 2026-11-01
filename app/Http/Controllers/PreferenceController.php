<?php

namespace App\Http\Controllers;

use App\Services\PreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function update(Request $request, PreferenceService $preferences): RedirectResponse
    {
        // An unchecked checkbox sends nothing, so read it as a boolean.
        $preferences->set($request->user(), ['celebrations' => $request->boolean('celebrations')]);

        return back()->with('success', 'Preferences saved.');
    }
}
