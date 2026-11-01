<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:due-reminders')]
#[Description('Notify assignees of open tasks due tomorrow or one day overdue (each reminder once)')]
class SendDueReminders extends Command
{
    public function handle(NotificationService $notifications): int
    {
        $sent = $notifications->sendDueReminders();

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
