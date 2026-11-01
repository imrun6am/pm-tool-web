<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Ai\Concerns\RespondsWithAi;
use App\Http\Controllers\Controller;
use App\Models\MeetingNote;
use App\Models\Release;
use App\Models\ReleaseDocument;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\FeedbackDrafter;
use App\Services\Ai\MeetingActionExtractor;
use App\Services\Ai\ScheduleAssistant;
use App\Services\Ai\ScoreEvidence;
use App\Services\Ai\SuggestedTaskStore;
use App\Services\Ai\TaskWriter;
use App\Services\Ai\ThreadCatchUp;
use App\Services\Ai\WorkspaceAsk;
use App\Support\PerformancePeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * The on-demand Claude features. Each action authorizes against the record it
 * reads, then hands off to its service; the services never decide access.
 */
class AiAssistController extends Controller
{
    use RespondsWithAi;

    // -- #2 Meeting notes → tasks -----------------------------------------

    public function meetingActions(Request $request, MeetingNote $meetingNote, MeetingActionExtractor $extractor): JsonResponse
    {
        $this->authorize('view', $meetingNote);

        return $this->aiJson(fn () => $extractor->propose($meetingNote, $request->user()));
    }

    public function storeMeetingTasks(Request $request, MeetingNote $meetingNote, SuggestedTaskStore $store): JsonResponse
    {
        $this->authorize('view', $meetingNote);
        $data = $request->validate(SuggestedTaskStore::rules());

        $release = $meetingNote->release && $request->user()->can('view', $meetingNote->release) ? $meetingNote->release : null;
        $created = $store->create($data['tasks'], $request->user(), $release);

        return response()->json(['created' => count($created), 'message' => self::createdMessage(count($created))]);
    }

    // -- #5 Task writer ---------------------------------------------------

    public function draftTask(Request $request, TaskWriter $writer): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'release_id' => ['nullable', 'integer', 'exists:releases,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
        ]);

        $release = isset($data['release_id']) ? Release::find($data['release_id']) : null;
        if ($release && ! $request->user()->can('view', $release)) {
            $release = null; // context only: never describe a release the user cannot open
        }
        $task = isset($data['task_id']) ? Task::find($data['task_id']) : null;
        if ($task) {
            $this->authorize('view', $task);
        }

        return $this->aiJson(fn () => $writer->draft($data['title'], $data['description'] ?? null, $release ?? $task?->release, $request->user(), $task));
    }

    public function storeSubtasks(Request $request, Task $task, SuggestedTaskStore $store): JsonResponse
    {
        $this->authorize('view', $task);
        abort_if($task->isSubtask(), 422, 'A subtask cannot have its own subtasks.');
        $data = $request->validate(SuggestedTaskStore::rules());

        $created = $store->create($data['tasks'], $request->user(), parent: $task);

        return response()->json(['created' => count($created), 'message' => self::createdMessage(count($created), 'subtask')]);
    }

    // -- #6 Catch me up ---------------------------------------------------

    public function catchUpTask(Request $request, Task $task, ThreadCatchUp $catchUp): JsonResponse
    {
        $this->authorize('view', $task);

        return $this->aiJson(fn () => $catchUp->summarize($task, $request->user()));
    }

    public function catchUpRelease(Request $request, Release $release, ThreadCatchUp $catchUp): JsonResponse
    {
        $this->authorize('view', $release);

        return $this->aiJson(fn () => $catchUp->summarize($release, $request->user()));
    }

    // -- #7 Document → tasks ----------------------------------------------

    public function storeDocumentTasks(Request $request, Release $release, ReleaseDocument $document, SuggestedTaskStore $store): JsonResponse
    {
        abort_unless($document->release_id === $release->id, 404);
        $this->authorize('view', $release);
        abort_if($request->user()->isViewer(), 403);
        $data = $request->validate(SuggestedTaskStore::rules());

        $created = $store->create($data['tasks'], $request->user(), $release);

        return response()->json(['created' => count($created), 'message' => self::createdMessage(count($created))]);
    }

    // -- #8 Schedule assistant --------------------------------------------

    public function schedule(Request $request, ScheduleAssistant $assistant): JsonResponse
    {
        abort_unless($request->user()->canManageReleases(), 403);
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'release_id' => ['nullable', 'integer', 'exists:releases,id'],
            'off_days' => ['nullable', 'array', 'max:366'],
        ]);

        $release = isset($data['release_id']) ? Release::with('offDays')->find($data['release_id']) : null;
        // The form may send plain dates or {date, reason} rows; keep the valid dates.
        $offDays = collect($data['off_days'] ?? [])
            ->map(fn ($d) => is_array($d) ? ($d['date'] ?? null) : $d)
            ->filter(fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
            ->merge($release?->offDays->map(fn ($d) => Carbon::parse($d->date)->toDateString()) ?? [])
            ->unique()->values()->all();

        return $this->aiJson(fn () => $assistant->suggest(
            Carbon::parse($data['start_date'])->startOfDay(),
            Carbon::parse($data['end_date'])->startOfDay(),
            Team::findOrFail($data['team_id']),
            $offDays,
            $release,
            $request->user(),
        ));
    }

    // -- #9 Ask your workspace --------------------------------------------

    public function askPage(): View
    {
        return view('ai.ask');
    }

    public function ask(Request $request, WorkspaceAsk $ask): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->aiJson(fn () => $ask->ask($data['question'], $request->user()));
    }

    // -- #10 Performance evidence -----------------------------------------

    public function scoreEvidence(Request $request, ScoreEvidence $evidence): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'user_id' => ['required', 'integer'],
            'cadence' => ['required', 'string'],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $team = Team::findOrFail($data['team_id']);
        abort_unless($request->user()->canAccessTeamPerformance($team), 403);
        $member = User::withTrashed()->findOrFail($data['user_id']);
        abort_unless($team->memberRecords()->whereKey($member->id)->exists(), 404);

        $period = PerformancePeriod::normalize($data['cadence'], Carbon::parse($data['date']));

        return $this->aiJson(fn () => $evidence->summarize($team, $member, $period['start'], $period['end'], $request->user()));
    }

    // -- #11 Tasksheet feedback drafts ------------------------------------

    public function feedbackDraft(Request $request, FeedbackDrafter $drafter): JsonResponse
    {
        abort_unless($request->user()->isLead(), 403); // feedback is lead-only, like the field itself
        $data = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'plan' => ['nullable', 'string', 'max:20000'],
            'result' => ['nullable', 'string', 'max:20000'],
            'comment' => ['nullable', 'string', 'max:20000'],
            'tickets' => ['nullable', 'string', 'max:20000'],
            'leave' => ['nullable', 'string', 'max:50'],
        ]);

        return $this->aiJson(fn () => $drafter->draft(
            Team::findOrFail($data['team_id']),
            User::findOrFail($data['user_id']),
            Carbon::parse($data['date']),
            $data,
            $request->user(),
        ));
    }

    private static function createdMessage(int $n, string $noun = 'task'): string
    {
        return $n.' '.str($noun)->plural($n).' added.';
    }
}
