<?php

namespace App\Console\Commands;

use App\Models\AiInsight;
use App\Models\Team;
use App\Services\Ai\AiException;
use App\Services\Ai\Insights\AiInsightService;
use App\Services\Ai\Insights\StandupDigestGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Queues a standup digest for every active team that filled the tasksheet on
 * the given day (default: the previous weekday). Scheduled each weekday
 * morning in routes/console.php; leads find the result on the tasksheet.
 */
class GenerateStandupDigests extends Command
{
    protected $signature = 'ai:standup-digests {--date= : Day to summarize (Y-m-d); defaults to the previous weekday}';

    protected $description = 'Queue Claude standup digests for teams with tasksheet entries on a day';

    public function handle(AiInsightService $insights): int
    {
        $date = Carbon::parse($this->option('date') ?: today()->subWeekday())->startOfDay();
        $teams = StandupDigestGenerator::teamsWithActivity(Team::active()->get(), $date);

        $queued = 0;
        foreach ($teams as $team) {
            if ($insights->latestReady(AiInsight::KIND_STANDUP_DIGEST, $team, $date)) {
                continue; // already summarized (e.g. a lead generated it by hand)
            }
            try {
                $insights->request(AiInsight::KIND_STANDUP_DIGEST, $team, null, $date);
                $queued++;
            } catch (AiException $e) {
                $this->warn($e->getMessage());

                return self::SUCCESS; // switched off or unconfigured: nothing to do for any team
            }
        }

        $this->info("Queued {$queued} standup ".str('digest')->plural($queued)." for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
