<?php

use App\Models\Activity;
use App\Models\AiUsageLog;
use App\Models\Task;
use App\Models\TasksheetExport;
use App\Support\LogRetention;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expired tasksheet exports and their files (TasksheetExport::prunable()), tasks
// past their time in the trash (Task::prunable()), and logs past LOG_RETENTION_MONTHS.
Schedule::command('model:prune', ['--model' => [TasksheetExport::class, Task::class, Activity::class, AiUsageLog::class]])->daily();

// Failed queue jobs past the same retention period.
Schedule::command('queue:prune-failed', ['--hours' => LogRetention::hours()])->daily();

// Tasks from recurring rules, ready before the day starts.
Schedule::command('tasks:recur')->dailyAt('06:00');

// Inbox reminders: tasks due tomorrow, and on their first day overdue.
Schedule::command('notifications:due-reminders')->dailyAt('08:00');

// Claude standup digests for the previous weekday, ready before standup.
Schedule::command('ai:standup-digests')->weekdays()->at('08:30');
