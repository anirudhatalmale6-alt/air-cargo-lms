<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'awb_number', 'driver', 'latest_status', 'latest_location',
    'latest_event_at', 'events', 'raw_response', 'fetched_at',
])]
class TrackingSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'raw_response' => 'array',
            'latest_event_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }
}
