<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin's overrides for one Claude feature (see config/ai.php): on/off,
 * model and effort. A null column keeps the config default.
 */
class AiFeatureSetting extends Model
{
    protected $primaryKey = 'feature';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['feature', 'enabled', 'model', 'effort', 'updated_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
