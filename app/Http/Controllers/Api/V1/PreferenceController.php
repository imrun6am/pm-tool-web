<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\PreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferenceController extends ApiController
{
    /** Partial update of the caller's editable preferences; returns them all. */
    public function update(Request $request, PreferenceService $preferences): JsonResponse
    {
        $data = $request->validate([
            'celebrations' => ['sometimes', 'boolean'],
        ]);

        $user = $preferences->set($request->user(), array_map('boolval', $data));

        return $this->ok($preferences->all($user), 'Preferences saved.');
    }
}
