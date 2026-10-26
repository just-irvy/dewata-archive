<?php

namespace App\Providers;

use App\Support\NeutralPasswordResetResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

/**
 * Fortify bersifat headless. Provider ini hanya memasang tampilan Blade milik arsip.
 * Logika autentikasi, rate limiter login, dan action pembuatan user tetap dari
 * FortifyServiceProvider hasil `php artisan fortify:install`.
 */
class AuthViewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, NeutralPasswordResetResponse::class);
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
    }
}
