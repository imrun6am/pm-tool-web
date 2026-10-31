<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleaseDocument extends Model
{
    protected $fillable = [
        'release_id', 'uploaded_by', 'original_name', 'path', 'mime_type', 'size',
    ];

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
