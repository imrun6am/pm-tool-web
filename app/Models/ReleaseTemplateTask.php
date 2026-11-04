<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseTemplateTask extends Model
{
    protected $fillable = ['release_template_id', 'title', 'phase', 'priority', 'estimate_minutes', 'position'];

    protected function casts(): array
    {
        return ['estimate_minutes' => 'integer', 'position' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ReleaseTemplate::class, 'release_template_id');
    }
}
