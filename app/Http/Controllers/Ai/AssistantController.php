<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AssistantAction;
use App\Services\Ai\AiException;
use App\Services\Ai\AiFeatures;
use App\Services\Ai\Assistant\AssistantAgent;
use App\Services\Ai\Assistant\AssistantPresenter;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The floating assistant. Messages answer NDJSON as they go (status, text,
 * reset, action, error, done); everything else is plain JSON. One person's
 * requests take turns on a lock, so a confirm can't land in the middle of a
 * turn that is rewriting the same transcript.
 */
class AssistantController extends Controller
{
    private const LOCK_SECONDS = 180;

    public function __construct(
        private readonly AssistantAgent $agent,
        private readonly AiFeatures $features,
    ) {}

    /** The popup's HTML, fetched the first time it opens. */
    public function panel(Request $request): View
    {
        return view('assistant.panel', [
            'available' => $this->features->available(AssistantAgent::FEATURE),
            'user' => $request->user(),
        ]);
    }

    public function conversation(Request $request): JsonResponse
    {
        return response()->json(['items' => AssistantPresenter::items($this->agent->current($request->user()))]);
    }

    public function message(Request $request): StreamedResponse|JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'page' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->stream($request, fn (callable $emit) => $this->agent->send($request->user(), trim($data['message']), $data['page'] ?? null, $emit));
    }

    /** Let the model carry on once confirmed changes have run. */
    public function resume(Request $request): StreamedResponse|JsonResponse
    {
        return $this->stream($request, fn (callable $emit) => $this->agent->resume($request->user(), $emit));
    }

    public function confirm(Request $request, AssistantAction $action): JsonResponse
    {
        $this->authorize('resolve', $action);

        return $this->inTurn($request, function () use ($request, $action) {
            $followUp = $this->agent->confirm($action, $request->user());

            return response()->json([
                'action' => AssistantPresenter::action($action->refresh()),
                'follow_up' => $followUp && $this->features->available(AssistantAgent::FEATURE),
            ]);
        });
    }

    public function cancel(Request $request, AssistantAction $action): JsonResponse
    {
        $this->authorize('resolve', $action);

        return $this->inTurn($request, function () use ($action) {
            $this->agent->cancel($action);

            return response()->json(['action' => AssistantPresenter::action($action->refresh())]);
        });
    }

    /** New chat. */
    public function destroy(Request $request): JsonResponse
    {
        return $this->inTurn($request, function () use ($request) {
            $this->agent->reset($request->user());

            return response()->json(['items' => []]);
        });
    }

    private function stream(Request $request, callable $turn): StreamedResponse|JsonResponse
    {
        if (! $this->features->available(AssistantAgent::FEATURE)) {
            $exception = $this->features->configured()
                ? AiException::disabled($this->features->get(AssistantAgent::FEATURE)['label'])
                : AiException::notConfigured();

            return response()->json(['message' => $exception->getMessage()], 409);
        }

        $lock = $this->lock($request);
        if (! $lock->get()) {
            return response()->json(['message' => 'The assistant is still working on your last message.'], 409);
        }

        return response()->stream(function () use ($request, $turn, $lock) {
            // Let a turn finish if the page goes away, so a reload finds the answer.
            ignore_user_abort(true);
            set_time_limit(self::LOCK_SECONDS);

            $emit = function (array $event) {
                echo json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
                if (! app()->runningUnitTests()) {
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    flush();
                }
            };

            try {
                $turn($emit);
            } catch (AiException $e) {
                $emit(['type' => 'error', 'message' => $e->getMessage(), 'retryable' => $e->retryable]);
            } finally {
                $lock->release();
            }

            $emit(['type' => 'done', 'items' => AssistantPresenter::items($this->agent->current($request->user()))]);
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function inTurn(Request $request, callable $action): JsonResponse
    {
        try {
            return $this->lock($request)->block(5, $action);
        } catch (LockTimeoutException) {
            return response()->json(['message' => 'The assistant is still working on your last message. Try again in a moment.'], 409);
        }
    }

    private function lock(Request $request): Lock
    {
        return Cache::lock('assistant:'.$request->user()->id, self::LOCK_SECONDS);
    }
}
