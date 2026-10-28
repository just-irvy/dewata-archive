<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;

/**
 * Permintaan reset untuk email yang tidak terdaftar menerima respons yang sama
 * dengan email terdaftar, supaya halaman ini tidak membocorkan siapa saja yang
 * punya akun (FILE 15, credential attacks). Pembatasan laju tetap dilaporkan.
 */
class NeutralPasswordResetResponse implements FailedPasswordResetLinkRequestResponse
{
    public function __construct(protected string $status)
    {
    }

    public function toResponse($request)
    {
        if ($this->status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['email' => [trans($this->status)]]);
        }

        $message = __('ui.auth.reset_link_neutral');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message], 200)
            : back()->with('status', $message);
    }
}
