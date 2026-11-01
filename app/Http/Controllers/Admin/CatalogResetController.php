<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ResetCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Admin: hapus permanen semua tempat dan hotel. Hanya super-admin, dengan frasa konfirmasi. */
class CatalogResetController extends Controller
{
    public function show(ResetCatalog $reset): View
    {
        Gate::authorize('reset-catalog');

        return view('admin.catalog-reset', ['preview' => $reset->preview(), 'phrase' => ResetCatalog::CONFIRMATION]);
    }

    public function destroy(Request $request, ResetCatalog $reset): RedirectResponse
    {
        Gate::authorize('reset-catalog');

        $request->validate([
            'confirmation' => ['required', 'string', Rule::in([ResetCatalog::CONFIRMATION])],
            'password' => ['required', 'current_password'],
        ], [
            'confirmation.in' => 'Frasa konfirmasi tidak cocok. Ketik persis: '.ResetCatalog::CONFIRMATION,
            'password.current_password' => 'Kata sandi salah.',
        ], ['confirmation' => 'frasa konfirmasi', 'password' => 'kata sandi']);

        $done = $reset($request->user());

        return redirect()->route('admin.index')
            ->with('status', "Katalog dikosongkan: {$done['places']} tempat dan {$done['hotels']} hotel dihapus permanen, beserta {$done['media']} foto.");
    }
}
