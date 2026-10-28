<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'totals' => 'array',
            'notes' => 'array',
            'committed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class)->orderBy('row_number');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isStaged(): bool
    {
        return $this->status === 'staged';
    }

    public function isCommitted(): bool
    {
        return $this->status === 'committed';
    }
}
