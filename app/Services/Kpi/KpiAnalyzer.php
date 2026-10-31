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
     *     days: list<array{date: string, team: string, planned: int, added: int, completed: int}>,
     *     months: list<array{month: string, feedback: string, strengths: string, improvement_areas: string, improvement_action: string}>,
     *     overall: array{feedback: string, strengths: string, improvement_areas: string, improvement_action: string},
     *     usage: array{input_tokens: int, output_tokens: int},
     * }
     */
    public function analyse(array $input): array;
}
