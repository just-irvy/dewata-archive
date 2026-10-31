<?php

namespace App\Http\Controllers\Site\Planner;

use App\Content\PlaceFacets;
use App\Enums\Pace;
use App\Enums\TransportMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Planner\ManualPlanRequest;
use App\Http\Requests\Planner\RecommendRequest;
use App\Models\Hotel;
use App\Models\Region;
use App\Planner\GuestDrafts;
use App\Planner\PlanAccess;
use App\Planner\PlanFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** /planner: pilih cara memulai, wizard Rekomendasi, dan composer Manual (FILE 03 dan FILE 10). */
class PlannerController extends Controller
{
    public function index(Request $request, GuestDrafts $drafts): View
    {
        $user = $request->user();

        return view('planner.index', [
            'recent' => $user
                ? $user->tripPlans()->latest('updated_at')->limit(3)->get()
                : $drafts->all()->take(3),
        ]);
    }

    public function recommend(Request $request, PlaceFacets $facets, PlanAccess $access): View
    {
        $prefill = [];
        if ($request->filled('dari')) {
            $source = $access->resolve($request, $request->query('dari'));
            $prefill = $source->canRecommend() ? $source->planner_answers : [];
        }

        $initialStep = 1;
        $errors = session('errors');
        if ($errors && $errors->any()) {
            $steps = array_map(
                fn ($key) => RecommendRequest::STEPS[explode('.', $key)[0]] ?? 6,
                $errors->getBag('default')->keys(),
            );
            $initialStep = $steps === [] ? 6 : min($steps);
        }

        return view('planner.recommend', [
            'regions' => $this->regionsWithPlaces(),
            'themes' => $facets->themes(),
            'access' => $facets->accessibility(),
            'hotelRegions' => Region::query()->published()
                ->whereHas('hotels', fn (Builder $q) => $q->published())
                ->orderBy('name')->get(),
            'hotelTypes' => Hotel::query()->published()->distinct()->pluck('type')
                ->mapWithKeys(fn ($type) => [$type->value => $type->label()])->sortKeys()->all(),
            'paces' => Pace::cases(),
            'transports' => TransportMode::cases(),
            'prefill' => $prefill,
            'initialStep' => $initialStep,
        ]);
    }

    public function generate(RecommendRequest $request, PlanFactory $factory): RedirectResponse
    {
        ['plan' => $plan, 'draft' => $draft] = $factory->recommended(
            $request->preferences(),
            $request->user(),
            $request->input('title'),
        );

        if ($plan === null) {
            return back()->withInput()->withErrors(['generate' => implode(' ', $draft->notes)]);
        }

        return redirect(PlanAccess::editorUrl($plan))
            ->with('status', 'Draf itinerary sudah disusun. Periksa alasan pemilihan, lalu ubah, pindah, atau hapus tempat sesuai kebutuhan.');
    }

    public function manual(): View
    {
        return view('planner.manual', [
            'regions' => $this->regionsWithPlaces(),
            'transports' => TransportMode::cases(),
        ]);
    }

    public function createManual(ManualPlanRequest $request, PlanFactory $factory): RedirectResponse
    {
        $plan = $factory->manual($request->validated(), $request->user());

        return redirect(PlanAccess::editorUrl($plan))
            ->with('status', 'Arsip kosong dibuat dengan '.$plan->days_count.' hari. Cari tempat dari katalog lalu tambahkan ke hari yang Anda pilih.');
    }

    private function regionsWithPlaces()
    {
        return Region::query()->published()
            ->whereHas('places', fn (Builder $q) => $q->published())
            ->withCount(['places' => fn (Builder $q) => $q->published()])
            ->orderBy('name')->get();
    }
}
