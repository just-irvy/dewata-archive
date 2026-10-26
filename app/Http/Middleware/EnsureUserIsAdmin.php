<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deny-by-default untuk area admin. Dipasang setelah middleware 'auth'.
 * Pengguna tanpa role admin menerima 403, bukan redirect.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        abort_unless($user->isAdmin(), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
