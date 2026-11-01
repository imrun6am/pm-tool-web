<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An admin's on/off override for one Claude feature (see config/ai.php). */
class AiFeatureSetting extends Model
{
    protected $primaryKey = 'feature';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['feature', 'enabled', 'updated_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
