<?php

namespace App\Http\Controllers\Site\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('account.index', [
            'plans' => $user->tripPlans()->withCount('items')->latest('updated_at')->limit(3)->get(),
            'planCount' => $user->tripPlans()->count(),
            'savedCount' => $user->savedPlaces()->count(),
        ]);
    }
}
