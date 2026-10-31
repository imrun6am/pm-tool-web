<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\MeetingNote;
use App\Models\Note;
use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\QuickLink;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Every enumeration the domain uses, published in one call.
 *
 * A desktop client renders its status pickers, type filters, and visibility
 * menus from this rather than embedding its own copy of the lists. That means
 * a status or role added server-side reaches every installed client on its
 * next request, instead of waiting for a client release to catch up — and it
 * removes the class of bug where the two copies quietly disagree.
 */
class MetaController extends ApiController
{
    public function index(): JsonResponse
    {
        return $this->ok([
            'roles' => $this->options(User::ROLES),
            'lead_roles' => User::LEAD_ROLES,

            'task_statuses' => $this->options(Task::STATUSES, Task::STATUS_COLORS),
            'task_done_statuses' => Task::DONE_STATUSES,

            'release_phases' => $this->options(Release::PHASES, Release::PHASE_COLORS),

            'event_types' => $this->options(Event::TYPES, Event::TYPE_COLORS),

            'meeting_note_types' => $this->options(MeetingNote::TYPES, MeetingNote::TYPE_COLORS),

            'note_visibilities' => $this->options(Note::VISIBILITIES),
            'meeting_note_visibilities' => $this->options(MeetingNote::VISIBILITIES),
            'quick_link_visibilities' => $this->options(QuickLink::VISIBILITIES),

            'tasksheet_leave_types' => $this->options(TasksheetEntry::LEAVE_TYPES),
            'tasksheet_full_day_leave_types' => TasksheetEntry::FULL_DAY_LEAVE_TYPES,
            'tasksheet_task_fields' => TasksheetEntry::TASK_FIELDS,

            'competency_categories' => $this->options(PerformanceCompetency::CATEGORIES),
            'competency_role_scopes' => $this->options(PerformanceCompetency::ROLE_SCOPES),
            'competency_cadences' => $this->options(PerformanceCompetency::CADENCES),

            'performance_scale' => [
                'min' => PerformanceScore::MIN_SCORE,
                'max' => PerformanceScore::MAX_SCORE,
                'anchors' => $this->options(PerformanceScore::SCALE),
            ],

            'document_upload' => [
                'max_size_kb' => 20480,
                'allowed_extensions' => [
                    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                    'txt', 'csv', 'png', 'jpg', 'jpeg', 'zip',
                ],
            ],

            'pagination' => [
                'default_per_page' => self::PER_PAGE,
                'max_per_page' => self::MAX_PER_PAGE,
            ],
        ]);
    }

    /**
     * Turn a `key => label` constant into a list of option objects, optionally
     * merging a matching `key => color` map. A list is used rather than an
     * object so the server's ordering survives JSON serialization.
     *
     * @param  array<array-key, string>  $labels
     * @param  array<array-key, string>  $colors
     * @return list<array<string, mixed>>
     */
    private function options(array $labels, array $colors = []): array
    {
        $out = [];

        foreach ($labels as $value => $label) {
            $option = ['value' => $value, 'label' => $label];

            if (isset($colors[$value])) {
                $option['color'] = $colors[$value];
            }

            $out[] = $option;
        }

        return $out;
    }
}
