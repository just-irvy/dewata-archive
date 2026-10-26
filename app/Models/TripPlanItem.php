<?php

namespace App\Models;

use App\Enums\ItemSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPlanItem extends Model
{
    protected $fillable = [
        'place_id', 'item_source', 'recommendation_reason', 'start_time',
        'duration_minutes', 'sort_order', 'note',
        'route_distance_m', 'route_duration_s', 'route_calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'item_source' => ItemSource::class,
            'route_calculated_at' => 'datetime',
        ];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(TripPlanDay::class, 'trip_plan_day_id');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class)->withTrashed();
    }
}
