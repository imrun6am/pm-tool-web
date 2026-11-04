<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:due-reminders')]
#[Description('Remind assignees of tasks due tomorrow or one day overdue, and attendees of events starting tomorrow (each reminder once)')]
class SendDueReminders extends Command
{
    public function handle(NotificationService $notifications): int
    {
        $sent = $notifications->sendDueReminders() + $notifications->sendEventReminders();

        $this->info("Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }
}
