<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Whether the viewer may generate it depends on the team, so controllers check DailyStandupPolicy. */
class GenerateStandupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->whereNull('archived_at')],
            'date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function team(): Team
    {
        return Team::findOrFail($this->validated('team_id'));
    }

    public function day(): Carbon
    {
        return Carbon::parse($this->validated('date') ?? today())->startOfDay();
    }
}
