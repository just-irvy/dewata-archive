<?php

namespace App\Models;

use App\Enums\TagType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'tag_type', 'description'];

    protected function casts(): array
    {
        return ['tag_type' => TagType::class];
    }

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(Place::class, 'place_tag');
    }
}
