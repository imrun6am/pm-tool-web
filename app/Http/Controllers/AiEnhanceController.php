<?php

namespace App\Http\Controllers;

use App\Services\Ai\TextEnhancer;
use App\Services\Kpi\MissingApiKeyException;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * "Enhance with Claude" for the note and meeting-note editors. Returns the
 * polished HTML for the editor to show; nothing is saved here.
 */
class AiEnhanceController extends Controller
{
    public function __invoke(Request $request, TextEnhancer $enhancer): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(TextEnhancer::KINDS)],
            'body' => ['required', 'string', 'max:20000', function (string $attribute, mixed $value, \Closure $fail) {
                if (HtmlSanitizer::isEmpty($value)) {
                    $fail('Write something first, then enhance it.');
                }
            }],
            'title' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);

        try {
            $html = $enhancer->enhance($data['kind'], HtmlSanitizer::clean($data['body']), [
                'title' => $data['title'] ?? null,
                'type' => $data['type'] ?? null,
            ]);
        } catch (MissingApiKeyException) {
            return response()->json(['message' => 'Claude isn’t configured (ANTHROPIC_API_KEY). Your text is unchanged.'], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Claude couldn’t enhance this right now. Your text is unchanged — please try again.'], 503);
        }

        return response()->json(['html' => $html]);
    }
}
