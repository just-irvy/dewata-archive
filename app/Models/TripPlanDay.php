<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripPlanDay extends Model
{
    protected $fillable = [
        'day_number', 'date', 'region_id', 'hotel_id', 'return_to_hotel',
        'title', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'return_to_hotel' => 'boolean',
        ];
    }

    public function tripPlan(): BelongsTo
    {
        return $this->belongsTo(TripPlan::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TripPlanItem::class)->orderBy('sort_order');
    }
}
