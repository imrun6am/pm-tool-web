<?php

namespace App\Services\Ai;

use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** What a plain-English question to the workspace is after: its search words, its topics and any period or task it names. */
final class AskQuestion
{
    private const STOPWORDS = ['what', 'when', 'where', 'which', 'who', 'whom', 'whose', 'why', 'how', 'the', 'and', 'for', 'are', 'was',
        'were', 'is', 'did', 'does', 'do', 'has', 'have', 'had', 'with', 'from', 'that', 'this', 'these', 'those', 'about', 'into', 'any',
        'all', 'our', 'my', 'me', 'we', 'us', 'you', 'your', 'can', 'could', 'should', 'would', 'will', 'there', 'their', 'them', 'they',
        'show', 'tell', 'list', 'give', 'find', 'last', 'next', 'this', 'week', 'month', 'today', 'still', 'been', 'being', 'not',
        // On their own these match half the workspace: a named one still matches by its name.
        'team', 'teams', 'task', 'tasks', 'release', 'releases', 'project', 'projects', 'current', 'please', 'update', 'updates', 'status',
        'many', 'much', 'right', 'now', 'get', 'need', 'like'];

    /** Wording that asks for more than a keyword search can find. Risk data is always sent, so it isn't listed. */
    private const TOPICS = [
        'progress' => '/\b(risk|risky|late|slip\w*|behind|delay\w*|on[- ]track|off[- ]track|on[- ]time|deadline|forecast\w*|finish\w*|eta|burn[- ]?up|progress|scope|health|ship\w*)\b|when will/',
        'capacity' => '/\b(capacity|bandwidth|availab\w*|free|room|overload\w*|over-?allocat\w*|workload|busy|assign|leave|vacation|absent|holiday|away)\b|who can|pick (this |it )?up|take (this |it )?on|days? off|off[- ]days?/',
        'flow' => '/\b(cycle|throughput|velocity|flow|aging|ageing|bottleneck\w*|wip|arrivals?|stuck|slow\w*)\b|lead time|in progress|too long/',
        'time' => '/\b(hours?|logged|timesheet|estimates?|estimated|accuracy|spent)\b|time log|how long|how much time|over budget/',
        'requests' => '/\b(requests?|triage|incoming|intake|reported)\b|bug reports?/',
        'notifications' => '/\b(notifications?|notified|mentions?|inbox|unread|alerts?)\b|did i miss|assigned to me/',
        'tasksheet' => '/\b(tasksheets?|stand-?ups?|day[- ]end|attend\w*|leave|vacation|absent|sick|yesterday)\b|work(ed|ing) on|who.s off|who is off|plan for/',
        'changes' => '/\b(chang\w*|new|recent\w*|lately|happen\w*|since|shipped|finished|completed|delivered|closed|moved|yesterday)\b|catch me up|what.s up|(last|this|past) (week|month|\d+ (days?|weeks?))/',
        'meetings' => '/\b(meetings?|decid\w*|decisions?|agreed|discuss\w*|stand-?ups?|sync\w*|retro\w*|minutes|kick-?off|day[- ]end)\b|action items?/',
        'notes' => '/\bnotes?\b|\bwrote\b/',
    ];

    /**
     * @param  Collection<int, string>  $words
     * @param  list<string>  $topics
     * @param  list<int>  $taskIds
     * @param  array{from: Carbon, to: Carbon}|null  $period
     */
    private function __construct(
        public readonly string $text,
        public readonly Collection $words,
        public readonly array $topics,
        public readonly array $taskIds,
        public readonly ?array $period,
    ) {}

    public static function parse(string $question): self
    {
        $text = mb_strtolower($question);

        $words = collect(preg_split('/[^\p{L}\p{N}\-\.]+/u', $text))
            ->map(fn ($w) => trim($w, '.-'))
            ->filter(fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, self::STOPWORDS, true))
            // A crude stem so "coupons" still finds "Coupon" (LIKE matches substrings).
            ->map(fn ($w) => mb_strlen($w) > 4 && str_ends_with($w, 's') && ! str_ends_with($w, 'ss') ? mb_substr($w, 0, -1) : $w)
            ->unique()->take(8)->values();

        preg_match_all('/\b'.Task::KEY_PREFIX.'-?\d+\b/i', $question, $references);

        return new self(
            $question,
            $words,
            array_keys(array_filter(self::TOPICS, fn (string $pattern) => preg_match($pattern, $text) === 1)),
            collect($references[0])->map(fn (string $reference) => Task::idFromReference($reference))->filter()->unique()->values()->all(),
            self::periodIn($text),
        );
    }

    public function asks(string ...$topics): bool
    {
        return array_intersect($topics, $this->topics) !== [];
    }

    /**
     * The period named in the question, or the last `$days` days.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function periodOr(int $days): array
    {
        return $this->period ?? ['from' => today()->subDays($days - 1), 'to' => today()];
    }

    /** @return array{from: Carbon, to: Carbon}|null */
    private static function periodIn(string $text): ?array
    {
        return match (true) {
            str_contains($text, 'yesterday') => ['from' => today()->subDay(), 'to' => today()->subDay()],
            str_contains($text, 'today') => ['from' => today(), 'to' => today()],
            str_contains($text, 'last week') => ['from' => today()->subWeek()->startOfWeek()->startOfDay(), 'to' => today()->subWeek()->endOfWeek()->startOfDay()],
            str_contains($text, 'this week') => ['from' => today()->startOfWeek()->startOfDay(), 'to' => today()],
            str_contains($text, 'last month') => ['from' => today()->subMonthNoOverflow()->startOfMonth(), 'to' => today()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            str_contains($text, 'this month') => ['from' => today()->startOfMonth(), 'to' => today()],
            (bool) preg_match('/(?:last|past) (\d{1,3}) days?/', $text, $m) => ['from' => today()->subDays(max(1, (int) $m[1]) - 1), 'to' => today()],
            (bool) preg_match('/(?:last|past) (\d{1,2}) weeks?/', $text, $m) => ['from' => today()->subWeeks(max(1, (int) $m[1])), 'to' => today()],
            default => null,
        };
    }
}
