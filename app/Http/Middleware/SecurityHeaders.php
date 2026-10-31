<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Header keamanan dasar untuk semua respons web (FILE 15 dan FILE 20). */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('archive.security_headers')) {
            return $response;
        }

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Geolokasi, kamera, dan mikrofon tidak dipakai. Peta tidak membutuhkan lokasi pengguna.
        $headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Halaman pribadi (akun, admin, editor rencana) tidak boleh disimpan cache bersama.
        if ($request->user() !== null || $request->is('planner/drafts/*')) {
            $headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
