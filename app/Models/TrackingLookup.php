<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['awb_number', 'driver', 'found', 'from_cache', 'duration_ms', 'error', 'ip', 'created_at'])]
class TrackingLookup extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'found' => 'boolean',
            'from_cache' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
