<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Media;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use App\Policies\AdminOnlyPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class ArchiveServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Kebijakan password: 10 karakter, dan di production dicek terhadap kebocoran data.
        Password::defaults(fn () => Password::min(10)->when(
            $this->app->isProduction(),
            fn (Password $rule) => $rule->uncompromised(),
        ));

        // Operasi berisiko tinggi hanya untuk super-admin. Tidak ada Gate::before,
        // sehingga super-admin tetap tunduk pada policy kepemilikan rencana.
        foreach ([Region::class, Category::class, Tag::class, Media::class] as $model) {
            Gate::policy($model, AdminOnlyPolicy::class);
        }

        Gate::define('view-audit-logs', fn ($user) => $user->isSuperAdmin());
        Gate::define('manage-roles', fn ($user) => $user->isSuperAdmin());

        // Status pembaruan data untuk footer. Di-cache agar tidak query tiap halaman.
        View::composer('components.site-footer', function ($view) {
            $timestamp = Cache::remember('archive.last_content_update', now()->addMinutes(10), function () {
                $value = Place::query()->published()->max('updated_at');

                return $value ? Carbon::parse($value)->toIso8601String() : null;
            });

            $view->with('lastUpdated', $timestamp ? Carbon::parse($timestamp) : null);
        });
    }
}
