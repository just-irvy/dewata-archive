<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    public const HOME_COVER = 'home_cover';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function value(string $key): ?array
    {
        return Cache::rememberForever('site_setting.'.$key, fn () => static::query()->where('key', $key)->value('value') ?? null);
    }

    public static function put(string $key, ?array $value): self
    {
        $setting = static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('site_setting.'.$key);

        return $setting;
    }

    /**
     * Foto sampul beranda, hanya bila lengkap: berkas ada, alt text, dan hak pakai.
     *
     * @return array{url: string, path: string, alt_text: string, caption: ?string, credit: ?string, license_note: ?string, width: ?int, height: ?int}|null
     */
    public static function homeCover(): ?array
    {
        $cover = static::value(self::HOME_COVER);

        if (! $cover || blank($cover['path'] ?? null) || blank($cover['alt_text'] ?? null)
            || (blank($cover['credit'] ?? null) && blank($cover['license_note'] ?? null))) {
            return null;
        }

        return $cover + ['url' => Storage::disk(config('archive.media.disk'))->url($cover['path'])];
    }
}
