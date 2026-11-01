<?php

use App\Models\TasksheetExport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expired tasksheet exports and their files (TasksheetExport::prunable()).
Schedule::command('model:prune', ['--model' => [TasksheetExport::class]])->daily();

// Inbox reminders: tasks due tomorrow, and on their first day overdue.
Schedule::command('notifications:due-reminders')->dailyAt('08:00');

// Claude standup digests for the previous weekday, ready before standup.
Schedule::command('ai:standup-digests')->weekdays()->at('08:30');
