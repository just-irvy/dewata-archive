<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu pesan percakapan asisten milik pengguna yang login. */
class ChatMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['role', 'text', 'card_keys', 'notes'];

    protected function casts(): array
    {
        return [
            'card_keys' => 'array',
            'notes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
