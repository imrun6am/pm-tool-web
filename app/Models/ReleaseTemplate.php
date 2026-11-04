<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A standard set of tasks to start a release with. */
class ReleaseTemplate extends Model
{
    use RecordsActivity;

    protected $fillable = ['name', 'created_by'];

    public function tasks(): HasMany
    {
        return $this->hasMany(ReleaseTemplateTask::class)->orderBy('position')->orderBy('id');
    }

    protected function activityExtraIgnored(): array
    {
        return ['created_by'];
    }
}
