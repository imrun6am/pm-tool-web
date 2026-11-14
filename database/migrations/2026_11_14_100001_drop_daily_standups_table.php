<?php

use App\Support\HtmlSanitizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daily standup generation is gone. Each note it made becomes an ordinary
 * meeting note: the tasksheet listing is copied into its body, its type goes
 * back to "meeting", and it is shown to its attendees and leads only, since the
 * listing holds one team's tasksheet. Then the generated rows are dropped.
 *
 * meeting_notes.created_by stays nullable: these notes never had an author, and
 * naming one now would credit someone with a note they didn't write.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_standups')) {
            return;
        }

        DB::table('daily_standups')
            ->join('meeting_notes', 'meeting_notes.id', '=', 'daily_standups.meeting_note_id')
            ->select('meeting_notes.id', 'meeting_notes.body', 'daily_standups.content')
            ->orderBy('meeting_notes.id')
            ->each(function (object $row) {
                // Query builder writes: no activity log, and updated_at stays as it was.
                DB::table('meeting_notes')->where('id', $row->id)->update([
                    'body' => $this->body((array) json_decode((string) $row->content, true), (string) $row->body),
                    'type' => 'meeting',
                    'visibility' => 'attendees',
                ]);
            });

        Schema::drop('daily_standups');
    }

    /** The table comes back empty; converted notes stay as they are. */
    public function down(): void
    {
        Schema::create('daily_standups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('meeting_note_id')->nullable()->constrained()->nullOnDelete();
            $table->json('content');
            $table->char('fingerprint', 32);
            $table->text('search_text');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('attendees_edited_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'date']);
        });
    }

    /**
     * The generated listing as note HTML, followed by whatever people wrote.
     * Tasksheet fields were stored as sanitized HTML; names and AI text are plain.
     *
     * @param  array<string, mixed>  $content
     */
    private function body(array $content, string $written): string
    {
        $day = fn (?string $date) => $date ? Carbon::parse($date)->format('D, M j') : null;
        $line = fn (string $label, string $text) => '<div><strong>'.e($label).':</strong> '.e($text).'</div>';
        $counts = (array) ($content['counts'] ?? []);

        $html = '<div><em>From the tasksheet: '.(int) ($counts['filled'] ?? 0).' of '.(int) ($counts['people'] ?? 0)
            .' filled in, '.(int) ($counts['on_leave'] ?? 0).' on leave.</em></div>';

        if ($onLeave = (array) ($content['on_leave'] ?? [])) {
            $html .= $line('On leave', collect($onLeave)->map(fn ($p) => ($p['name'] ?? '?').' ('.($p['leave'] ?? 'leave').')')->join(', '));
        }
        if ($notFilled = (array) ($content['not_filled'] ?? [])) {
            $html .= $line('Not filled in', collect($notFilled)->pluck('name')->join(', '));
        }

        if ($ai = $content['ai'] ?? null) {
            $html .= '<h1>AI summary of '.e($day($ai['covers'] ?? null) ?? 'the previous day').'</h1><div>'.e($ai['summary'] ?? '').'</div>';
            if ($blockers = (array) ($ai['blockers'] ?? [])) {
                $html .= '<div><strong>Blockers</strong></div><ul>'.collect($blockers)
                    ->map(fn ($b) => '<li>'.e(trim(($b['member'] ?? '') !== '' ? "{$b['member']}: {$b['text']}" : (string) ($b['text'] ?? ''))).'</li>')
                    ->join('').'</ul>';
            }
        }

        $previous = $day($content['previous_date'] ?? null);
        $today = $day($content['date'] ?? null);
        $fields = fn (array $work, array $labels) => collect($labels)
            ->filter(fn (string $label, string $field) => ! empty($work[$field]))
            ->map(fn (string $label, string $field) => '<div><strong>'.e($label).'</strong></div>'.$work[$field])
            ->join('');

        foreach ((array) ($content['people'] ?? []) as $person) {
            $did = (array) ($person['previous'] ?? []);
            $doing = (array) ($person['today'] ?? []);

            $html .= '<h1>'.e($person['name'] ?? '?').(! empty($doing['leave']) ? ' ('.e($doing['leave']).')' : '').'</h1>';
            $html .= $fields($did, ['result' => 'Did'.($previous ? " ({$previous})" : ''), 'tickets' => 'Tickets', 'comment' => 'Comment'])
                ?: '<div><strong>Did'.($previous ? ' ('.e($previous).')' : '').':</strong> '.e($did['leave'] ?? 'Nothing logged').'</div>';
            $html .= $fields($doing, ['plan' => 'Today'.($today ? " ({$today})" : ''), 'result' => 'Done so far', 'tickets' => 'Tickets', 'comment' => 'Comment'])
                ?: '<div><strong>Today:</strong> '.e($doing['leave'] ?? 'No plan').'</div>';
        }

        if (! HtmlSanitizer::isEmpty($written)) {
            $html .= '<h1>Meeting notes</h1>'.$written;
        }

        return HtmlSanitizer::clean($html);
    }
};
