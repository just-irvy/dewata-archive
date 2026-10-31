<?php

namespace App\Planner;

use App\Models\TripPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Satu-satunya jalan membuka rencana dari URL. ID dari browser tidak dipercaya:
 * rencana user dicari lewat scope pemilik lalu policy, draf tamu lewat token cookie.
 * Selain itu 404, agar keberadaan rencana orang lain tidak bisa ditebak.
 */
final class PlanAccess
{
    public function __construct(private readonly GuestDrafts $drafts) {}

    public function resolve(Request $request, int|string $id): TripPlan
    {
        $id = (int) $id;
        $user = $request->user();

        if ($user !== null) {
            $plan = TripPlan::query()->ownedBy($user)->find($id);
            if ($plan !== null) {
                Gate::forUser($user)->authorize('update', $plan);

                return $plan;
            }
        }

        return $this->drafts->find($id) ?? abort(404);
    }

    public static function editorUrl(TripPlan $plan, array $query = []): string
    {
        return $plan->user_id !== null
            ? route('account.plans.show', ['plan' => $plan->id] + $query)
            : route('planner.drafts.show', ['plan' => $plan->id] + $query);
    }
}
