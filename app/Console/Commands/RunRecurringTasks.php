<?php

namespace App\Console\Commands;

use App\Services\RecurringTaskService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tasks:recur')]
#[Description('Create tasks from recurring rules that are due')]
class RunRecurringTasks extends Command
{
    public function handle(RecurringTaskService $recurring): int
    {
        $created = $recurring->runDue();

        $this->info("Created {$created} task(s).");

        return self::SUCCESS;
    }
}
