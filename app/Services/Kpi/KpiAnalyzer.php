<?php

namespace App\Services\Kpi;

/**
 * Turns one member's tasksheet entries into task counts and review drafts.
 * Bound to ClaudeKpiAnalyzer; tests bind a fake.
 */
interface KpiAnalyzer
{
    /**
     * @param  array{role: string, teams: list<string>, months: list<string>, signals: array<string, array<string, int|float>>, entries: list<array<string, mixed>>}  $input
     * @return array{
     *     days: list<array{date: string, team: string, planned: int, added: int, completed: int, categories: list<array{category: string, tasks: int}>}>,
     *     months: list<array{month: string, focus: string, feedback: string, strengths: string, improvement_areas: string, improvement_action: string}>,
     *     overall: array{
     *         feedback: string, strengths: string, improvement_areas: string, improvement_action: string,
     *         key_contributions: list<string>,
     *         recurring_tasks: list<array{task: string, days: int, comment: string}>,
     *         carried_over: list<array{task: string, first_date: string, last_date: string, days: int}>,
     *         observations: list<array{type: 'positive'|'concern', text: string}>,
     *         suggested_rating: array{score: int, rationale: string},
     *     },
     *     usage: array{input_tokens: int, output_tokens: int},
     * }
     */
    public function analyse(array $input): array;
}
