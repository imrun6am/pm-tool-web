<?php

use App\Models\Activity;
use App\Models\AiUsageLog;
use App\Models\AssistantConversation;
use App\Models\Task;
use App\Models\TasksheetExport;
use App\Support\LogRetention;
use App\Support\WorkingWeek;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expired tasksheet exports and their files (TasksheetExport::prunable()), tasks
// past their time in the trash (Task::prunable()), and logs past LOG_RETENTION_MONTHS.
Schedule::command('model:prune', ['--model' => [TasksheetExport::class, Task::class, Activity::class, AiUsageLog::class, AssistantConversation::class]])->daily();

// Failed queue jobs past the same retention period.
Schedule::command('queue:prune-failed', ['--hours' => LogRetention::hours()])->daily();

// Tasks from recurring rules, ready before the day starts.
Schedule::command('tasks:recur')->dailyAt('06:00');

// Inbox reminders: tasks due tomorrow, and on their first day overdue.
Schedule::command('notifications:due-reminders')->dailyAt('08:00');

// AI standup digests for the previous working day, ready before standup.
Schedule::command('ai:standup-digests')->dailyAt('08:30')->when(fn () => WorkingWeek::isWorkingDay(today()));

// Each team's standup note, from its tasksheets. Starts after the digest so it can
// quote it, and refreshes while plans (09:00-10:00) and results (18:00-20:00) come
// in. Teams whose tasksheets haven't changed write nothing.
Schedule::command('standup:generate')->everyFifteenMinutes()->between('08:45', '20:00')->when(fn () => WorkingWeek::isWorkingDay(today()));
