<?php

namespace App\Models;

use App\Enums\PriceBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPriceSnapshot extends Model
{
    protected $fillable = [
        'price_min', 'price_max', 'currency', 'price_basis', 'source_url', 'checked_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'price_basis' => PriceBasis::class,
            'checked_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
