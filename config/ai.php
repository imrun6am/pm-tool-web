<?php

/*
|--------------------------------------------------------------------------
| Claude features
|--------------------------------------------------------------------------
|
| Every Claude call made through App\Services\Ai\AiGateway names one of the
| features below. A feature's model and effort come from here; on the AI
| usage page an admin can switch a feature off and change its model and
| effort at runtime (stored in ai_feature_settings), which overrides these
| defaults. The API key itself lives in config/services.php (ANTHROPIC_API_KEY).
|
*/

$model = env('AI_DEFAULT_MODEL', 'claude-sonnet-5-5');

return [

    'default_model' => $model,

    // Models an admin may pick on the AI usage page.
    'models' => [
        'claude-sonnet-5-5' => 'Sonnet 5.5 — balanced (default)',
        'claude-haiku-5-5' => 'Haiku 5.5 — fastest, cheapest',
        'claude-opus-5-5' => 'Opus 5.5 — most capable, costliest',
    ],

    // Models without the server-side refusal fallback: the gateway leaves it
    // out for them, and the untracked features (which always send it) can't use them.
    'no_fallback' => ['claude-haiku-5-5'],

    'efforts' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'],

    'features' => [
        // Called directly through the SDK (streaming / Files API), not through
        // AiGateway: only their model and effort come from here, and their
        // calls are not logged on the usage page. `tracked` marks the difference.
        'kpi_review' => [
            'label' => 'KPI reports',
            'description' => 'Admin KPI review reports drafted from tasksheets.',
            'model' => env('KPI_REVIEW_MODEL', $model),
            'effort' => env('KPI_REVIEW_EFFORT', 'medium'),
            'enabled' => true,
            'tracked' => false,
        ],
        'calendar_import' => [
            'label' => 'Calendar import',
            'description' => 'Finds events in uploaded files for the calendar.',
            'model' => env('CALENDAR_IMPORT_MODEL', $model),
            'effort' => env('CALENDAR_IMPORT_EFFORT', 'medium'),
            'enabled' => true,
            'tracked' => false,
        ],
        // Existing features, moved onto the gateway; their original env names still apply.
        'note_enhance' => [
            'label' => 'Enhance with Claude',
            'description' => 'Polishes notes and meeting minutes in the editor.',
            'model' => env('ENHANCE_MODEL', $model),
            'effort' => env('ENHANCE_EFFORT', 'low'),
            'enabled' => true,
        ],
        'plan_to_board' => [
            'label' => 'Tasksheet → board',
            'description' => 'Splits a member’s daily plan into board tasks.',
            'model' => env('BOARD_TASKS_MODEL', $model),
            'effort' => env('BOARD_TASKS_EFFORT', 'low'),
            'enabled' => true,
        ],
        'release_notes' => [
            'label' => 'Release notes',
            'description' => 'Drafts release notes from a release’s finished tasks.',
            'model' => env('AI_RELEASE_NOTES_MODEL', $model),
            'effort' => env('AI_RELEASE_NOTES_EFFORT', 'low'),
            'enabled' => true,
        ],
        'meeting_actions' => [
            'label' => 'Meeting notes → tasks',
            'description' => 'Finds action items in meeting minutes and proposes board tasks.',
            'model' => env('AI_MEETING_ACTIONS_MODEL', $model),
            'effort' => env('AI_MEETING_ACTIONS_EFFORT', 'low'),
            'enabled' => true,
        ],
        'release_risk' => [
            'label' => 'Release risk brief',
            'description' => 'On-track / at-risk assessment of a release, with a stakeholder update.',
            'model' => env('AI_RELEASE_RISK_MODEL', $model),
            'effort' => env('AI_RELEASE_RISK_EFFORT', 'medium'),
            'enabled' => true,
        ],
        'standup_digest' => [
            'label' => 'Daily standup digest',
            'description' => 'Summarizes a team’s tasksheet and board movement for its lead.',
            'model' => env('AI_STANDUP_DIGEST_MODEL', $model),
            'effort' => env('AI_STANDUP_DIGEST_EFFORT', 'low'),
            'enabled' => true,
        ],
        'task_writer' => [
            'label' => 'Task writer',
            'description' => 'Turns a one-line title into a description, acceptance criteria and subtasks.',
            'model' => env('AI_TASK_WRITER_MODEL', $model),
            'effort' => env('AI_TASK_WRITER_EFFORT', 'low'),
            'enabled' => true,
        ],
        'catch_up' => [
            'label' => 'Catch me up',
            'description' => 'Summarizes a long comment thread on a task or release.',
            'model' => env('AI_CATCH_UP_MODEL', $model),
            'effort' => env('AI_CATCH_UP_EFFORT', 'low'),
            'enabled' => true,
        ],
        'document_insights' => [
            'label' => 'Document → requirements',
            'description' => 'Summarizes an uploaded release document and suggests tasks from it.',
            'model' => env('AI_DOCUMENT_INSIGHTS_MODEL', $model),
            'effort' => env('AI_DOCUMENT_INSIGHTS_EFFORT', 'medium'),
            'enabled' => true,
        ],
        'schedule_assistant' => [
            'label' => 'Release schedule assistant',
            'description' => 'Suggests phase dates around off-days and team double-bookings.',
            'model' => env('AI_SCHEDULE_ASSISTANT_MODEL', $model),
            'effort' => env('AI_SCHEDULE_ASSISTANT_EFFORT', 'low'),
            'enabled' => true,
        ],
        'workspace_ask' => [
            'label' => 'Ask your workspace',
            'description' => 'Answers plain-English questions from the data the asker may see.',
            'model' => env('AI_WORKSPACE_ASK_MODEL', $model),
            'effort' => env('AI_WORKSPACE_ASK_EFFORT', 'medium'),
            'enabled' => true,
        ],
        'score_evidence' => [
            'label' => 'Performance evidence',
            'description' => 'Summarizes a member’s tasksheet and board record for the lead scoring them. Never suggests a score.',
            'model' => env('AI_SCORE_EVIDENCE_MODEL', $model),
            'effort' => env('AI_SCORE_EVIDENCE_EFFORT', 'low'),
            'enabled' => true,
        ],
        'feedback_draft' => [
            'label' => 'Tasksheet feedback drafts',
            'description' => 'Drafts constructive lead feedback on a tasksheet entry.',
            'model' => env('AI_FEEDBACK_DRAFT_MODEL', $model),
            'effort' => env('AI_FEEDBACK_DRAFT_EFFORT', 'low'),
            'enabled' => true,
        ],
    ],

    /*
     | USD per million tokens, for the usage page's cost estimate only (billing
     | is Anthropic's). Cache reads are billed separately at the cache rate.
     */
    'pricing' => [
        'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00, 'cache_read' => 0.20],
        'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00, 'cache_read' => 0.20],
        'claude-haiku-5-5' => ['input' => 0.10, 'output' => 0.50, 'cache_read' => 0.01],
    ],

];
