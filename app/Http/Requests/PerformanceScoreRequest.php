<?php

namespace App\Http\Requests;

use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\Team;
use App\Models\User;
use App\Support\PerformancePeriod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Validates one evaluation-grid row save: a member's ratings across the
 * competencies of a single cadence for one period. The route is behind the
 * `lead` middleware; authorization here adds team scoping. Empty cells are
 * allowed (skipped by the controller) so a lead can rate a few at a time.
 */
class PerformanceScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        // Team-scope the evaluator: a missing team falls through to the `exists`
        // rule (422); a real team the lead may not touch is forbidden (403).
        $team = Team::find($this->integer('team_id'));

        return $team === null || $user->canAccessTeamPerformance($team);
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date'],
            'cadence' => ['required', Rule::in(array_keys(PerformanceCompetency::CADENCES))],
            'scores' => ['array'],
            'scores.*' => ['nullable', 'integer', 'between:'.PerformanceScore::MIN_SCORE.','.PerformanceScore::MAX_SCORE],
            'notes' => ['array'],
            'notes.*' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $cadence = $this->input('cadence');
            $anchor = Carbon::parse($this->input('date'));
            $period = PerformancePeriod::normalize($cadence, $anchor);

            // No rating a period that has not begun.
            if ($period['start']->gt(today())) {
                $validator->errors()->add('date', 'You cannot evaluate a future period.');

                return;
            }

            // The target must be an active developer/QA member of the team — or
            // already have a score for it (former members keep an editable record).
            $teamId = $this->integer('team_id');
            $userId = $this->integer('user_id');

            $isMember = Team::whereKey($teamId)
                ->whereHas('members', fn ($q) => $q->whereKey($userId)
                    ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA]))
                ->exists();
            $hasScore = PerformanceScore::where('team_id', $teamId)
                ->where('user_id', $userId)
                ->exists();

            if (! $isMember && ! $hasScore) {
                $validator->errors()->add('user_id', 'That person is not a developer or QA member of this team.');

                return;
            }

            // Every rated competency must belong to the submitted cadence, be
            // active, and apply to the member's role.
            $ratedIds = collect($this->input('scores', []))
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->keys();

            if ($ratedIds->isEmpty()) {
                return;
            }

            $member = User::find($userId);
            $competencies = PerformanceCompetency::whereKey($ratedIds)->get()->keyBy('id');

            foreach ($ratedIds as $id) {
                $competency = $competencies->get($id);
                $valid = $competency
                    && $competency->active
                    && $competency->cadence === $cadence
                    && ($member === null || $competency->appliesToRole($member->role));

                if (! $valid) {
                    $validator->errors()->add('scores.'.$id, 'That competency cannot be rated here.');
                }
            }
        });
    }
}
