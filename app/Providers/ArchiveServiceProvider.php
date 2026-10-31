<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Media;
use App\Models\Place;
use App\Models\Region;
use App\Models\Tag;
use App\Planner\ClaimGuestDrafts;
use App\Planner\GuestDrafts;
use App\Policies\AdminOnlyPolicy;
use App\Services\Maps\GoogleRoutesProvider;
use App\Services\Maps\NullRouteProvider;
use App\Services\Maps\RouteProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class ArchiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RouteProvider::class, fn () => filled(config('archive.maps.server_key'))
            ? new GoogleRoutesProvider
            : new NullRouteProvider);

        // Perubahan cookie dalam satu request terkumpul di satu instance. Cache token terikat ke objek request.
        $this->app->singleton(GuestDrafts::class);
    }

    public function boot(): void
    {
        TrustProxies::at(config('archive.trusted_proxies'));

        Event::listen(Login::class, [ClaimGuestDrafts::class, 'handle']);

        // Pembatasan laju (FILE 15: abuse dan biaya). Kunci memakai user bila login, IP bila tamu.
        $by = fn (Request $request) => $request->user()?->id ? 'u'.$request->user()->id : 'ip'.$request->ip();
        RateLimiter::for('planner-create', fn (Request $request) => Limit::perMinute(10)->by($by($request)));
        RateLimiter::for('planner-edit', fn (Request $request) => Limit::perMinute(120)->by($by($request)));
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(90)->by($by($request)));

        Paginator::defaultView('vendor.pagination.archive');
        Paginator::defaultSimpleView('vendor.pagination.archive');

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

        Gate::define('manage-imports', fn ($user) => $user->isAdmin());
        Gate::define('rollback-import', fn ($user) => $user->isSuperAdmin());
        Gate::define('view-audit-logs', fn ($user) => $user->isSuperAdmin());
        Gate::define('manage-roles', fn ($user) => $user->isSuperAdmin());

        // Status pembaruan data untuk footer. Di-cache agar tidak query tiap halaman.
        View::composer('components.site-footer', function ($view) {
            // Footer tidak boleh ikut menjatuhkan halaman error saat database bermasalah.
            try {
                $timestamp = Cache::remember('archive.last_content_update', now()->addMinutes(10), function () {
                    $value = Place::query()->published()->max('updated_at');

                    return $value ? Carbon::parse($value)->toIso8601String() : null;
                });
            } catch (\Throwable) {
                $timestamp = null;
            }

            $view->with('lastUpdated', $timestamp ? Carbon::parse($timestamp) : null);
        });
    }
}
