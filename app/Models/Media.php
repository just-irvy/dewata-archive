<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'path', 'alt_text', 'caption', 'credit', 'license_note',
        'width', 'height', 'sort_order',
    ];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /** Media bermakna tidak boleh terbit tanpa alt text dan keterangan hak pakai. */
    public function isPublishable(): bool
    {
        return filled($this->alt_text) && (filled($this->credit) || filled($this->license_note));
    }
}
