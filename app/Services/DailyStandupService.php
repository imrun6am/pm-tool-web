<?php

namespace App\Services;

use App\Models\AiInsight;
use App\Models\DailyStandup;
use App\Models\MeetingNote;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\Ai\AiFeatures;
use App\Services\Ai\Insights\AiInsightService;
use App\Services\Board\PlanToBoardService;
use App\Support\HtmlSanitizer;
use App\Support\StandupRun;
use App\Support\WorkingWeek;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps one standup meeting note per team and working day in step with the
 * tasksheets: what each person did on the previous working day, what they have
 * for the day itself, who is on leave and who hasn't filled it in.
 *
 * Safe to run any number of times. Only the DailyStandup row and, until someone
 * edits them by hand, the attendees are written, so nothing a person wrote is
 * touched. No AI is called here: a finished standup digest is quoted when there
 * is one, and everything else works without it. Nothing here notifies anyone.
 */
class DailyStandupService
{
    public function __construct(
        private readonly TasksheetService $tasksheet,
        private readonly AiFeatures $features,
        private readonly AiInsightService $insights,
    ) {}

    /** @param  bool  $restore  bring back a standup someone deleted; the schedule never does */
    public function generate(Team $team, Carbon $date, bool $restore = false): StandupRun
    {
        $date = $date->copy()->startOfDay();

        if (! WorkingWeek::isWorkingDay($date)) {
            return new StandupRun(StandupRun::DAY_OFF);
        }
        if ($date->isFuture()) {
            return new StandupRun(StandupRun::NOTHING_TO_REPORT);
        }

        $existing = DailyStandup::forTeamOn($team, $date)->first();
        if ($existing && ! $existing->meeting_note_id && ! $restore) {
            return new StandupRun(StandupRun::DELETED);
        }

        $entries = $this->tasksheet->entriesFor($team, $date);
        $content = $this->content($team, $date, $entries);

        if (! $existing?->meeting_note_id && ! $this->hasSomethingToReport($content)) {
            return new StandupRun(StandupRun::NOTHING_TO_REPORT);
        }

        return DB::transaction(fn () => $this->store($team, $date, $content, $this->attendeeIds($team, $entries)));
    }

    /** The teams the schedule looks at. */
    public function teams(): Collection
    {
        return Team::active()->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $content
     * @param  list<int>  $attendeeIds
     */
    private function store(Team $team, Carbon $date, array $content, array $attendeeIds): StandupRun
    {
        $generated = [
            'content' => $content,
            'fingerprint' => md5((string) json_encode($content)),
            'search_text' => $this->searchText($content),
            'generated_at' => now(),
        ];

        // The unique index settles two runs arriving together.
        $standup = DailyStandup::createOrFirst(['team_id' => $team->id, 'date' => $date], $generated);
        $isNew = ! $standup->meeting_note_id;
        $changed = $standup->fingerprint !== $generated['fingerprint'];

        if ($isNew) {
            $standup->meeting_note_id = $this->createNote($team, $date)->id;
            $standup->attendees_edited_at = null;
        }
        if ($changed) {
            $standup->fill($generated);
        }
        $standup->save();

        $note = $standup->meetingNote()->firstOrFail();
        $attendeesChanged = $this->syncAttendees($standup, $note, $attendeeIds);

        return new StandupRun(match (true) {
            $isNew => StandupRun::CREATED,
            $changed || $attendeesChanged => StandupRun::UPDATED,
            default => StandupRun::UNCHANGED,
        }, $note);
    }

    /** Without events: no user made this, and a line per team per day in the shared feed is noise. */
    private function createNote(Team $team, Carbon $date): MeetingNote
    {
        return MeetingNote::withoutEvents(fn () => MeetingNote::create([
            'created_by' => null,
            'title' => mb_strimwidth("{$team->name} standup · {$date->format('D, M j')}", 0, 255, '…'),
            'type' => MeetingNote::TYPE_STANDUP,
            'meeting_date' => $date,
            'body' => '',
            'visibility' => MeetingNote::VISIBILITY_EVERYONE,
        ]));
    }

    /** @param  list<int>  $attendeeIds */
    private function syncAttendees(DailyStandup $standup, MeetingNote $note, array $attendeeIds): bool
    {
        if ($standup->attendees_edited_at) {
            return false;
        }

        $changes = $note->attendees()->sync($attendeeIds);

        return $changes['attached'] !== [] || $changes['detached'] !== [];
    }

    /**
     * Who was expected: the team and its lead, minus anyone off for the day or
     * marked by a lead as having missed it.
     *
     * @param  Collection<int, TasksheetEntry>  $entries
     * @return list<int>
     */
    private function attendeeIds(Team $team, Collection $entries): array
    {
        $away = $entries->filter(fn (TasksheetEntry $e) => $e->isFullDayLeave() || $e->missedStandup())->keys();

        return User::active()
            ->where(fn ($q) => $q
                ->whereIn('users.id', $team->members()->select('users.id'))
                ->orWhere('users.id', $team->team_lead_id))
            ->pluck('users.id')
            ->diff($away)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TasksheetEntry>  $entries
     * @return array<string, mixed>
     */
    private function content(Team $team, Carbon $date, Collection $entries): array
    {
        $previousDate = WorkingWeek::previous($date);
        $previous = $this->tasksheet->entriesFor($team, $previousDate);
        $roster = $this->tasksheet->rowUsersFor($team, $date, $entries);

        $people = [];
        $onLeave = [];
        $notFilled = [];
        $filled = 0;

        foreach ($roster as $user) {
            $today = $entries->get($user->id);
            $before = $previous->get($user->id);
            $todayWork = $this->work($today);
            // Yesterday's plan isn't something that got done.
            $beforeWork = array_diff_key($this->work($before), ['plan' => true]);
            $person = ['id' => $user->id, 'name' => $user->name];

            if ($today?->isOnLeave()) {
                $onLeave[] = $person + ['leave' => $today->leaveLabel(), 'full_day' => $today->isFullDayLeave()];
            }
            if ($todayWork !== []) {
                $filled++;
            } elseif (! $today?->isFullDayLeave()) {
                $notFilled[] = $person;
            }
            if ($todayWork !== [] || $beforeWork !== []) {
                $people[] = $person + [
                    'previous' => $beforeWork + array_filter(['leave' => $before?->isOnLeave() ? $before->leaveLabel() : null]),
                    'today' => $todayWork + array_filter(['leave' => $today?->isOnLeave() ? $today->leaveLabel() : null]),
                ];
            }
        }

        return [
            'date' => $date->toDateString(),
            'previous_date' => $previousDate->toDateString(),
            'counts' => [
                'people' => $roster->count(),
                'filled' => $filled,
                'on_leave' => count($onLeave),
                'not_filled' => count($notFilled),
            ],
            'people' => $people,
            'on_leave' => $onLeave,
            'not_filled' => $notFilled,
            'ai' => $this->aiSummary($team, $previousDate),
        ];
    }

    /**
     * The only tasksheet text a standup may carry. Feedback, points, ticket
     * counts and standup attendance stay out by not being read here.
     *
     * @return array<string, string>
     */
    private function work(?TasksheetEntry $entry): array
    {
        if (! $entry) {
            return [];
        }

        return collect(['plan', 'result', 'tickets', 'comment'])
            ->mapWithKeys(fn (string $field) => [$field => $entry->{$field}])
            ->reject(fn (?string $html) => HtmlSanitizer::isEmpty($html))
            ->map(fn (string $html) => HtmlSanitizer::clean($html))
            ->all();
    }

    /**
     * The digest's summary and blockers for the day "did" covers. Its follow-ups
     * and attendance counts are for the lead and stay on the tasksheet.
     *
     * @return array{covers: string, summary: string, blockers: list<array{member: string, text: string}>}|null
     */
    private function aiSummary(Team $team, Carbon $previousDate): ?array
    {
        if (! $this->features->available(AiInsight::KIND_STANDUP_DIGEST)) {
            return null;
        }

        $payload = $this->insights->latestReady(AiInsight::KIND_STANDUP_DIGEST, $team, $previousDate)?->payload;
        $summary = trim((string) ($payload['summary'] ?? ''));

        // A digest of a day nobody filled in has nothing to add.
        if ($summary === '' || (($payload['filled'] ?? 0) === 0 && empty($payload['blockers']))) {
            return null;
        }

        return [
            'covers' => $previousDate->toDateString(),
            'summary' => $summary,
            'blockers' => array_values(array_map(
                fn (array $b) => ['member' => (string) ($b['member'] ?? ''), 'text' => (string) ($b['text'] ?? '')],
                array_filter((array) ($payload['blockers'] ?? []), 'is_array'),
            )),
        ];
    }

    /** @param  array<string, mixed>  $content */
    private function hasSomethingToReport(array $content): bool
    {
        return $content['people'] !== [] || $content['on_leave'] !== [];
    }

    /** @param  array<string, mixed>  $content */
    private function searchText(array $content): string
    {
        $text = fn (array $work) => collect($work)->except('leave')->map(fn (string $html) => PlanToBoardService::plainText($html))->values();

        return collect($content['people'])
            ->flatMap(fn (array $p) => [$p['name'], ...$text($p['previous']), ...$text($p['today'])])
            ->concat(collect($content['on_leave'])->pluck('name'))
            ->concat(array_filter([$content['ai']['summary'] ?? null]))
            ->implode("\n");
    }
}
