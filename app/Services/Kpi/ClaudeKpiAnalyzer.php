<?php

namespace App\Services\Kpi;

use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaRawContentBlockStartEvent;
use Anthropic\Beta\Messages\BetaRawMessageDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageStartEvent;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Client;
use RuntimeException;

/**
 * Asks Claude to count each day's planned / added / completed tasks from the
 * free-text Plan and Result, and to draft the Team Lead's review notes.
 *
 * One request per member. The response is streamed (long inputs, adaptive
 * thinking) and only the JSON text is kept, so memory stays flat.
 */
class ClaudeKpiAnalyzer implements KpiAnalyzer
{
    private const SYSTEM = <<<'TXT'
        You prepare a Team Lead's KPI review from a developer/QA team member's daily tasksheet.
        Each day has a morning PLAN and a day-end RESULT (free text, often "Task 1: ...", "- ..." lines, ticket numbers, project headings).

        Per day, count tasks:
        - planned: distinct tasks in PLAN. A project heading on its own is not a task; each bullet / "Task N" / ticket line under it is.
        - added: tasks that appear in RESULT but were not in PLAN (new work picked up during the day).
        - completed: tasks (planned or added) that RESULT reports as done or delivered. Not completed: work described as in progress, ongoing, "continue", pending, blocked, waiting, or planned tasks missing from RESULT.
        If RESULT is empty, completed is 0. If PLAN is empty but RESULT lists work, those tasks are "added".
        When RESULT just repeats PLAN verbatim with no status words, treat the tasks as completed.

        Write review drafts for the Team Lead, per month and for the whole period ("overall"), grounded only in the entries and signals (standup attendance, late or partial tasksheets, missing results, work points):
        - feedback: behaviour, performance and communication, 1-2 sentences.
        - strengths: where the member performs best, 1-2 concrete points.
        - improvement_areas: skills or habits to improve, 1-2 concrete points.
        - improvement_action: what the lead should do or provide to help, 1-2 concrete actions.
        Plain sentences, no names, no headings, no markdown. Do not invent facts that are not in the entries.
        TXT;

    private const NOTES = ['feedback', 'strengths', 'improvement_areas', 'improvement_action'];

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $effort,
    ) {}

    public function analyse(array $input): array
    {
        if (blank($this->apiKey)) {
            throw new MissingApiKeyException('ANTHROPIC_API_KEY is not set.');
        }

        $prompt = sprintf(
            "Role: %s. Teams: %s.\nReturn one \"days\" item for every entry below (%d entries, same date + team), one \"months\" item for each of: %s, and an \"overall\" summary.\n\nMonthly signals:\n%s\n\nEntries:\n%s",
            $input['role'],
            implode(', ', $input['teams']),
            count($input['entries']),
            implode(', ', $input['months']),
            json_encode($input['signals'], JSON_UNESCAPED_UNICODE),
            json_encode($input['entries'], JSON_UNESCAPED_UNICODE),
        );

        $stream = (new Client(apiKey: $this->apiKey))->beta->messages->createStream(
            model: $this->model,
            maxTokens: 64000,
            system: self::SYSTEM,
            messages: [['role' => 'user', 'content' => $prompt]],
            thinking: ['type' => 'adaptive'],
            outputConfig: ['effort' => $this->effort, 'format' => ['type' => 'json_schema', 'schema' => $this->schema()]],
            // A policy decline is re-run server-side on the recommended fallback model.
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
            requestOptions: ['timeout' => 600],
        );

        $json = '';
        $stopReason = null;
        $usage = ['input_tokens' => 0, 'output_tokens' => 0];

        foreach ($stream as $event) {
            if ($event instanceof BetaRawContentBlockDeltaEvent && $event->delta instanceof BetaTextDelta) {
                $json .= $event->delta->text;
            } elseif ($event instanceof BetaRawContentBlockStartEvent && $event->contentBlock->type === 'fallback') {
                $json = ''; // the declined model's partial text is superseded by the fallback model's answer
            } elseif ($event instanceof BetaRawMessageStartEvent) {
                $usage['input_tokens'] = $event->message->usage->inputTokens;
            } elseif ($event instanceof BetaRawMessageDeltaEvent) {
                $stopReason = $event->delta->stopReason;
                $usage['output_tokens'] = $event->usage->outputTokens;
            }
        }

        if ($stopReason === 'refusal') {
            throw new RuntimeException('Claude declined to analyse this member.');
        }
        if ($stopReason === 'max_tokens') {
            throw new RuntimeException('Claude ran out of output tokens.');
        }

        $out = json_decode($json, true);
        if (! is_array($out) || ! isset($out['days'], $out['months'], $out['overall'])) {
            throw new RuntimeException('Claude returned an unreadable response.');
        }

        $expected = collect($input['entries'])->map(fn ($e) => $e['date'].'|'.$e['team']);
        $returned = collect($out['days'])->map(fn ($d) => $d['date'].'|'.$d['team']);
        $missing = $expected->diff($returned);
        if ($missing->isNotEmpty()) {
            throw new RuntimeException("Claude skipped {$missing->count()} day(s), e.g. {$missing->first()}.");
        }

        return $out + ['usage' => $usage];
    }

    private function schema(): array
    {
        $notes = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => self::NOTES,
            'properties' => array_fill_keys(self::NOTES, ['type' => 'string']),
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['days', 'months', 'overall'],
            'properties' => [
                'days' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['date', 'team', 'planned', 'added', 'completed'],
                        'properties' => [
                            'date' => ['type' => 'string'],
                            'team' => ['type' => 'string'],
                            'planned' => ['type' => 'integer'],
                            'added' => ['type' => 'integer'],
                            'completed' => ['type' => 'integer'],
                        ],
                    ],
                ],
                'months' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['month', ...self::NOTES],
                        'properties' => ['month' => ['type' => 'string', 'description' => 'YYYY-MM']] + $notes['properties'],
                    ],
                ],
                'overall' => $notes,
            ],
        ];
    }
}
