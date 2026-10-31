<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Note;
use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    // Mute model events during seeding so activity logging (which needs an
    // authenticated causer) does not fire and flood the history.
    use WithoutModelEvents;

    public function run(): void
    {
        // --- Performance competency catalog (idempotent) -----------------
        $this->call(PerformanceCompetencySeeder::class);

        // --- Users -------------------------------------------------------
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => Hash::make('password'), 'role' => User::ROLE_ADMIN, 'email_verified_at' => now()]
        );

        $viewer = User::updateOrCreate(
            ['email' => 'viewer@example.com'],
            ['name' => 'Viewer User', 'password' => Hash::make('password'), 'role' => User::ROLE_VIEWER, 'email_verified_at' => now()]
        );

        // Additional roles (all log in with password `password`).
        User::updateOrCreate(['email' => 'cto@example.com'],
            ['name' => 'Casey CTO', 'password' => Hash::make('password'), 'role' => User::ROLE_CTO, 'email_verified_at' => now()]);
        User::updateOrCreate(['email' => 'techlead@example.com'],
            ['name' => 'Tariq Tech Lead', 'password' => Hash::make('password'), 'role' => User::ROLE_TECH_LEAD, 'email_verified_at' => now()]);
        User::updateOrCreate(['email' => 'lead@example.com'],
            ['name' => 'Lee Lead', 'password' => Hash::make('password'), 'role' => User::ROLE_TEAM_LEAD, 'email_verified_at' => now()]);
        User::updateOrCreate(['email' => 'dev@example.com'],
            ['name' => 'Devin Dev', 'password' => Hash::make('password'), 'role' => User::ROLE_DEVELOPER, 'email_verified_at' => now()]);
        User::updateOrCreate(['email' => 'qa@example.com'],
            ['name' => 'Quinn QA', 'password' => Hash::make('password'), 'role' => User::ROLE_QA, 'email_verified_at' => now()]);

        // --- Projects ----------------------------------------------------
        $checkout = Project::create(['name' => 'Checkout', 'color' => '#4f46e5', 'description' => 'Storefront checkout & payments.']);
        $mobile = Project::create(['name' => 'Mobile App', 'color' => '#10b981', 'description' => 'iOS & Android apps.']);
        $billing = Project::create(['name' => 'Billing', 'color' => '#e11d48', 'description' => 'Subscriptions & invoicing.']);
        $data = Project::create(['name' => 'Data Platform', 'color' => '#7c3aed', 'description' => 'Analytics & pipelines.']);

        // --- Teams -------------------------------------------------------
        $alpha = Team::create(['name' => 'Team Alpha', 'color' => '#0891b2', 'description' => 'Web platform squad.']);
        $bravo = Team::create(['name' => 'Team Bravo', 'color' => '#d97706', 'description' => 'Mobile squad.']);
        $charlie = Team::create(['name' => 'Team Charlie', 'color' => '#16a34a', 'description' => 'Data & billing squad.']);

        $year = (int) now()->year;

        // --- Releases (Team Alpha's first two overlap on purpose) --------
        $r1 = $this->makeRelease($checkout, $alpha, 'Checkout v2.4', $year, 3, "$year-07-10", "$year-07-30",
            'Revamp the checkout flow: new payment provider, address validation, and a faster confirmation step.');
        $this->makeRelease($billing, $alpha, 'Billing hotfix', $year, 3, "$year-07-20", "$year-08-05"); // OVERLAP
        $this->makeRelease($checkout, $alpha, 'Checkout v2.5', $year, 4, "$year-10-01", "$year-10-25");

        $this->makeRelease($mobile, $bravo, 'Mobile 3.0', $year, 3, "$year-08-01", "$year-08-28");
        $this->makeRelease($mobile, $bravo, 'Mobile 3.1', $year, 4, "$year-11-03", "$year-11-24");

        $ga = $this->makeRelease($data, $charlie, 'Data Platform GA', $year, 2, "$year-05-05", "$year-06-20");
        $ga->update([
            'completed_at' => Carbon::parse("$year-06-20 17:00:00"),
            'completed_by' => $admin->id,
            'completion_notes' => "Shipped on schedule. \n\n**Highlights**\n- New ingestion pipeline live\n- Dashboards migrated\n\n**Follow-ups**\n- Backfill historical data next quarter",
        ]);

        $this->makeRelease($billing, $charlie, 'Billing revamp', $year, 3, "$year-09-01", "$year-09-30");

        // --- Collaboration demo data on Checkout v2.4 --------------------
        $t1 = $r1->tasks()->create([
            'title' => 'Wire up new payment API', 'description' => 'Integrate the new provider SDK and handle webhooks.',
            'status' => 'in_progress', 'assignee_id' => $admin->id, 'created_by' => $admin->id,
            'phase' => 'development', 'due_date' => "$year-07-18", 'position' => 0,
        ]);
        $t1->subtasks()->create(['release_id' => $r1->id, 'title' => 'Add Stripe API keys to config', 'status' => 'done', 'assignee_id' => $admin->id, 'created_by' => $admin->id, 'position' => 0]);
        $t1->subtasks()->create(['release_id' => $r1->id, 'title' => 'Handle refunds & disputes', 'status' => 'todo', 'assignee_id' => $viewer->id, 'created_by' => $admin->id, 'position' => 1]);

        $r1->tasks()->create([
            'title' => 'Write QA test plan', 'status' => 'todo', 'assignee_id' => $viewer->id, 'created_by' => $viewer->id,
            'phase' => 'qa', 'position' => 1,
        ]);

        $r1->comments()->create(['user_id' => $admin->id, 'body' => 'Kickoff is scheduled for Monday — please review the scope.']);
        $r1->comments()->create(['user_id' => $viewer->id, 'body' => 'Looks good. One question about the refund flow — added a task comment.']);
        $t1->comments()->create(['user_id' => $viewer->id, 'body' => 'Blocked until we get the sandbox credentials from the provider.']);

        // Off-days: a holiday plus the weekends in the window.
        $r1->offDays()->create(['date' => "$year-07-14", 'reason' => 'Public holiday']);
        $cursor = $r1->start_date->copy();
        while ($cursor->lte($r1->end_date)) {
            if ($cursor->isWeekend() && $cursor->toDateString() !== "$year-07-14") {
                $r1->offDays()->create(['date' => $cursor->toDateString(), 'reason' => 'Weekend']);
            }
            $cursor->addDay();
        }

        // --- Calendar events --------------------------------------------
        $kickoff = Event::create([
            'title' => 'Checkout v2.4 kickoff', 'type' => 'meeting',
            'starts_at' => "$year-07-10 10:00:00", 'ends_at' => "$year-07-10 11:00:00",
            'location' => 'Room 4 / Zoom', 'release_id' => $r1->id, 'created_by' => $admin->id,
            'description' => 'Walk through scope and assign owners.',
        ]);
        $kickoff->attendees()->sync([$admin->id, $viewer->id]);

        Event::create([
            'title' => 'QA review', 'type' => 'review',
            'starts_at' => "$year-07-24 14:00:00", 'ends_at' => "$year-07-24 15:00:00",
            'release_id' => $r1->id, 'created_by' => $admin->id,
        ])->attendees()->sync([$admin->id]);

        Event::create([
            'title' => 'Checkout v2.4 release day', 'type' => 'release', 'all_day' => true,
            'starts_at' => "$year-07-29 00:00:00", 'ends_at' => "$year-07-30 23:59:59",
            'release_id' => $r1->id, 'created_by' => $admin->id,
        ]);

        // --- Daily notes ------------------------------------------------
        $today = now()->toDateString();
        Note::create([
            'user_id' => $admin->id, 'date' => $today, 'visibility' => 'shared',
            'body' => '<div>Standup at <strong>10am</strong> — focus on the <em>Checkout v2.4</em> release.</div><ul><li>Update your board cards</li><li>Flag any blockers</li></ul>',
        ]);
        Note::create([
            'user_id' => $viewer->id, 'date' => $today, 'visibility' => 'private',
            'body' => '<div>Reminder to myself: review the <strong>QA test plan</strong> before EOD.</div>',
        ]);
    }

    private function makeRelease(Project $project, Team $team, string $name, int $year, int $quarter, string $start, string $end, ?string $description = null): Release
    {
        $release = Release::create([
            'project_id' => $project->id,
            'team_id' => $team->id,
            'name' => $name,
            'description' => $description,
            'year' => $year,
            'quarter' => $quarter,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();
        $total = $s->diffInDays($e) + 1;

        $devEnd = $s->copy()->addDays((int) floor(($total - 1) * 0.40));
        $qaEnd = $s->copy()->addDays((int) floor(($total - 1) * 0.65));
        $retestEnd = $s->copy()->addDays((int) floor(($total - 1) * 0.80));

        $segments = [
            ['development', $s, $devEnd],
            ['qa', $devEnd->copy()->addDay(), $qaEnd],
            ['retest', $qaEnd->copy()->addDay(), $retestEnd],
            ['release', $retestEnd->copy()->addDay(), $e],
        ];

        $position = 0;
        foreach ($segments as [$phase, $pStart, $pEnd]) {
            if ($pStart->gt($e)) {
                $pStart = $e->copy();
            }
            if ($pEnd->lt($pStart)) {
                $pEnd = $pStart->copy();
            }
            if ($pEnd->gt($e)) {
                $pEnd = $e->copy();
            }

            $release->phases()->create([
                'phase' => $phase,
                'position' => $position++,
                'start_date' => $pStart->toDateString(),
                'end_date' => $pEnd->toDateString(),
            ]);
        }

        return $release;
    }
}
