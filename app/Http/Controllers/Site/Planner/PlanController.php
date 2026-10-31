<?php

namespace App\Http\Controllers\Site\Planner;

use App\Enums\TransportMode;
use App\Enums\TripPlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Planner\PlanUpdateRequest;
use App\Models\Hotel;
use App\Planner\ClaimGuestDrafts;
use App\Planner\GuestDrafts;
use App\Planner\ItineraryEditor;
use App\Planner\PlanAccess;
use App\Planner\PlanPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Editor bersama untuk draf tamu (/planner/drafts/{id}) dan arsip akun (/account/plans/{id}). */
class PlanController extends Controller
{
    public function __construct(private readonly PlanAccess $access) {}

    public function show(Request $request, int $plan, PlanPresenter $presenter): View|RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);

        // Satu URL kanonis per pemilik.
        $canonical = $model->user_id !== null ? 'account.plans.show' : 'planner.drafts.show';
        if (! $request->routeIs($canonical)) {
            return redirect(PlanAccess::editorUrl($model, $request->query()));
        }

        return view('planner.editor', $presenter->present($model, $request) + [
            'transports' => TransportMode::cases(),
        ]);
    }

    public function update(PlanUpdateRequest $request, int $plan, ItineraryEditor $editor, GuestDrafts $drafts): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);
        $editor->updatePlan($model, $request->validated());
        $drafts->touch($model);

        return redirect(PlanAccess::editorUrl($model))->with('status', 'Detail arsip diperbarui.');
    }

    /** Guest diarahkan ke masuk atau daftar. Draf dipindah ke akun setelah login (ClaimGuestDrafts). */
    public function save(Request $request, int $plan): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);

        if ($request->user() !== null && $model->user_id !== null) {
            $model->forceFill(['status' => TripPlanStatus::Saved])->save();

            return redirect(PlanAccess::editorUrl($model))->with('status', 'Arsip disimpan di akun Anda.');
        }

        if ($request->user() !== null) {
            app(ClaimGuestDrafts::class)->claim($request->user());
            $model->refresh()->forceFill(['status' => TripPlanStatus::Saved])->save();

            return redirect(PlanAccess::editorUrl($model))->with('status', 'Arsip disimpan di akun Anda.');
        }

        $request->session()->put(ClaimGuestDrafts::SAVE_AFTER_LOGIN, $model->id);
        $request->session()->put('url.intended', route('account.plans.show', $model->id));

        return redirect()->route('login')
            ->with('status', 'Masuk atau buat akun untuk menyimpan arsip ini. Draf Anda dipindah ke akun secara otomatis.');
    }

    public function destroy(Request $request, int $plan, GuestDrafts $drafts): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);
        $guest = $model->user_id === null;
        $model->delete();

        if ($guest) {
            $drafts->forget($plan);

            return redirect()->route('planner.index')->with('status', 'Draf dihapus.');
        }

        return redirect()->route('account.plans.index')->with('status', 'Arsip dihapus.');
    }

    /** Pakai satu penginapan sebagai titik awal semua hari. Bukan pemesanan. */
    public function useHotel(Request $request, int $plan, ItineraryEditor $editor, GuestDrafts $drafts): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);
        $data = $request->validate(['hotel_id' => ['required', 'integer']]);

        $hotel = Hotel::query()->published()->whereNotNull('lat')->whereNotNull('lng')->findOrFail($data['hotel_id']);
        $editor->setHotelForAllDays($model, $hotel);
        $drafts->touch($model);

        return redirect(PlanAccess::editorUrl($model))->with('status', $hotel->name.' dipakai sebagai titik awal rute untuk semua hari.');
    }

    public function index(Request $request): View
    {
        $plans = $request->user()->tripPlans()
            ->withCount('items')
            ->with('days.region')
            ->latest('updated_at')
            ->paginate(12);

        return view('account.plans', ['plans' => $plans, 'statuses' => TripPlanStatus::cases()]);
    }
}
