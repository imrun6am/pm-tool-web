<?php

namespace Database\Seeders;

use App\Models\PerformanceCompetency as C;
use Illuminate\Database\Seeder;

/**
 * The default competency framework. Idempotent (updateOrCreate on `key`), so it
 * is safe to re-run — it will not clobber admin edits to unrelated rows and only
 * refreshes the seeded definitions. Spans all four categories, both roles, and
 * both cadences (daily observations + weekly considered ratings).
 */
class PerformanceCompetencySeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            // key, name, category, role_scope, cadence, weight, description
            ['code-quality', 'Code Quality', C::CATEGORY_TECHNICAL, C::SCOPE_DEVELOPER, C::CADENCE_WEEKLY, 3,
                'Clean, readable, maintainable code that follows standards and needs little rework.'],
            ['problem-solving', 'Problem Solving', C::CATEGORY_TECHNICAL, C::SCOPE_BOTH, C::CADENCE_WEEKLY, 3,
                'Breaks down problems, weighs approaches, and reaches sound solutions independently.'],
            ['task-completion', 'Task Completion', C::CATEGORY_DELIVERY, C::SCOPE_BOTH, C::CADENCE_DAILY, 3,
                'Delivers committed work on time and to the agreed definition of done.'],
            ['understanding-requirements', 'Understanding & Requirements', C::CATEGORY_DELIVERY, C::SCOPE_BOTH, C::CADENCE_WEEKLY, 2,
                'Grasps requirements and context, asks the right questions, builds the right thing.'],
            ['behavior-professionalism', 'Behavior & Professionalism', C::CATEGORY_BEHAVIORAL, C::SCOPE_BOTH, C::CADENCE_DAILY, 2,
                'Reliable, respectful, and constructive conduct with the team day to day.'],
            ['communication-collaboration', 'Communication & Collaboration', C::CATEGORY_BEHAVIORAL, C::SCOPE_BOTH, C::CADENCE_WEEKLY, 2,
                'Communicates clearly, shares context, and collaborates well across the team.'],
            ['ownership-discipline', 'Ownership & Discipline', C::CATEGORY_BEHAVIORAL, C::SCOPE_BOTH, C::CADENCE_DAILY, 2,
                'Takes ownership, follows through, and keeps to process, standups, and the tasksheet.'],
            ['learning-progress', 'Learning Progress', C::CATEGORY_GROWTH, C::SCOPE_BOTH, C::CADENCE_WEEKLY, 2,
                'Grows skills over time, absorbs feedback, and picks up new tools and domains.'],
            ['test-thoroughness', 'Test Thoroughness', C::CATEGORY_TECHNICAL, C::SCOPE_QA, C::CADENCE_WEEKLY, 3,
                'Covers cases broadly — happy path, edge cases, and regressions — with solid test design.'],
            ['defect-detection', 'Defect Detection', C::CATEGORY_TECHNICAL, C::SCOPE_QA, C::CADENCE_WEEKLY, 3,
                'Finds real, meaningful defects early with clear, reproducible reports.'],
            ['attention-detail', 'Attention to Detail', C::CATEGORY_TECHNICAL, C::SCOPE_QA, C::CADENCE_DAILY, 2,
                'Catches the small things — precise, careful, and consistent in the work.'],
        ];

        foreach ($catalog as $position => [$key, $name, $category, $scope, $cadence, $weight, $description]) {
            C::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'description' => $description,
                    'category' => $category,
                    'role_scope' => $scope,
                    'cadence' => $cadence,
                    'weight' => $weight,
                    'position' => $position,
                    // Only default `active` on first insert — never re-activate a
                    // competency an admin has since deactivated.
                    'active' => C::where('key', $key)->value('active') ?? true,
                ]
            );
        }
    }
}
