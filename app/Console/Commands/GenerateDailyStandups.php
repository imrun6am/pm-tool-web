<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\DailyStandupService;
use App\Support\StandupRun;
use App\Support\WorkingWeek;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Creates or refreshes each active team's standup note for a working day from
 * its tasksheets. Scheduled through the working day in routes/console.php; safe
 * to run again at any time, since unchanged teams write nothing.
 */
class GenerateDailyStandups extends Command
{
    protected $signature = 'standup:generate
        {--date= : Day to generate (Y-m-d); defaults to today}
        {--team= : Only this team id}
        {--restore : Also bring back standups someone deleted}';

    protected $description = 'Create or update each team\'s daily standup note from its tasksheets';

    public function handle(DailyStandupService $standups): int
    {
        try {
            $date = Carbon::parse($this->option('date') ?: today())->startOfDay();
        } catch (InvalidFormatException) {
            $this->error('The date isn’t valid. Use Y-m-d.');

            return self::FAILURE;
        }

        if (! WorkingWeek::isWorkingDay($date)) {
            $this->info("{$date->toDateString()} isn't a working day, so there's nothing to do.");

            return self::SUCCESS;
        }

        $teams = $this->option('team')
            ? Team::active()->whereKey($this->option('team'))->get()
            : $standups->teams();

        if ($teams->isEmpty() && $this->option('team')) {
            $this->error('No active team has that id.');

            return self::FAILURE;
        }

        $counts = [];
        $failed = 0;

        foreach ($teams as $team) {
            try {
                $status = $standups->generate($team, $date, (bool) $this->option('restore'))->status;
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            } catch (Throwable $e) {
                // One team's bad data shouldn't cost the others their standup.
                report($e);
                $this->error("{$team->name}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info(sprintf(
            '%s: %d created, %d updated, %d unchanged, %d with nothing to report, %d deleted by hand%s.',
            $date->toDateString(),
            $counts[StandupRun::CREATED] ?? 0,
            $counts[StandupRun::UPDATED] ?? 0,
            $counts[StandupRun::UNCHANGED] ?? 0,
            $counts[StandupRun::NOTHING_TO_REPORT] ?? 0,
            $counts[StandupRun::DELETED] ?? 0,
            $failed ? ", {$failed} failed" : '',
        ));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
