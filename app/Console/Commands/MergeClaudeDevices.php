<?php

namespace App\Console\Commands;

use App\Services\ClaudeActivity\DeviceMerger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('claude-activity:merge-devices')]
#[Description('Merge Claude activity devices that reported the same MAC address (a computer the monitor was installed on twice)')]
class MergeClaudeDevices extends Command
{
    public function handle(DeviceMerger $merger): int
    {
        $folded = $merger->mergeAll();

        $this->info($folded === 0 ? 'No devices to merge.' : "Merged {$folded} device(s) into the device that first reported their MAC address.");

        return self::SUCCESS;
    }
}
