<?php

namespace App\Http\Controllers\Site\Planner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planner\ItemRequest;
use App\Models\Place;
use App\Models\TripPlan;
use App\Models\TripPlanDay;
use App\Models\TripPlanItem;
use App\Planner\GuestDrafts;
use App\Planner\ItineraryEditor;
use App\Planner\PlanAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    public function __construct(
        private readonly PlanAccess $access,
        private readonly ItineraryEditor $editor,
        private readonly GuestDrafts $drafts,
    ) {}

    /** Tambah tempat dari katalog terbit ke hari tertentu (mode Manual dan tambah tempat). */
    public function store(Request $request, int $plan): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);
        $data = $request->validate([
            'day_id' => ['required', 'integer'],
            'place_id' => ['required', 'integer'],
        ]);

        $day = $this->day($model, $data['day_id']);
        $place = $this->place($data['place_id']);
        $this->editor->addItem($model, $day, $place);
        $this->drafts->touch($model);

        return $this->back($model, $day->day_number, $place->name.' ditambahkan ke Hari '.$day->day_number.'.', keepSearch: $request);
    }

    /** Catatan, durasi, dan jam mulai. Dengan JavaScript disimpan tanpa memuat ulang halaman. */
    public function update(ItemRequest $request, int $plan, int $item): RedirectResponse|JsonResponse
    {
        $model = $this->access->resolve($request, $plan);
        $record = $this->item($model, $item);
        $this->editor->updateItem($record, $request->validated());
        $this->drafts->touch($model);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return $this->back($model, $record->day->day_number, 'Catatan '.$record->place->name.' disimpan.');
    }

    /** Pindah hari, pindah posisi, atau naik/turun satu langkah. */
    public function move(Request $request, int $plan, int $item): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);
        $record = $this->item($model, $item);
        $data = $request->validate([
            'direction' => ['nullable', Rule::in(['up', 'down'])],
            'day_id' => ['required_without:direction', 'nullable', 'integer'],
            'position' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if (! empty($data['direction'])) {
            $this->editor->shiftItem($record, $data['direction']);
            $target = $record->day;
        } else {
            $target = $this->day($model, $data['day_id']);
            $this->editor->moveItem($record, $target, isset($data['position']) ? (int) $data['position'] : null);
        }
        $this->drafts->touch($model);

        return $this->back($model, $target->day_number, $record->place->name.' dipindah. Rute hari terkait dihitung ulang saat peta dibuka.');
    }

    public function replace(Request $request, int $plan, int $item): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);
        $record = $this->item($model, $item);
        $data = $request->validate(['place_id' => ['required', 'integer']]);

        $old = $record->place->name;
        $place = $this->place($data['place_id']);
        $this->editor->replaceItem($model, $record, $place);
        $this->drafts->touch($model);

        return $this->back($model, $record->day->day_number, $old.' diganti dengan '.$place->name.'.');
    }

    public function destroy(Request $request, int $plan, int $item): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);
        $record = $this->item($model, $item);
        $number = $record->day->day_number;
        $name = $record->place->name;
        $this->editor->removeItem($record);
        $this->drafts->touch($model);

        return $this->back($model, $number, $name.' dihapus dari Hari '.$number.'.');
    }

    private function day(TripPlan $plan, int $id): TripPlanDay
    {
        return $plan->days()->whereKey($id)->first()
            ?? throw ValidationException::withMessages(['day_id' => 'Hari tidak ditemukan pada arsip ini.']);
    }

    private function item(TripPlan $plan, int $id): TripPlanItem
    {
        return TripPlanItem::query()
            ->whereKey($id)
            ->whereIn('trip_plan_day_id', $plan->days()->pluck('id'))
            ->with(['day', 'place'])
            ->firstOrFail();
    }

    private function place(int $id): Place
    {
        return Place::query()->published()->find($id)
            ?? throw ValidationException::withMessages(['place_id' => 'Tempat ini tidak tersedia di katalog terbit.']);
    }

    private function back(TripPlan $plan, int $dayNumber, string $message, ?Request $keepSearch = null): RedirectResponse
    {
        $query = ['hari' => $dayNumber];
        if ($keepSearch) {
            $query += array_filter(['cari' => $keepSearch->input('cari'), 'wilayah' => $keepSearch->input('wilayah')]);
        }

        return redirect(PlanAccess::editorUrl($plan, $query).'#hari-'.$dayNumber)->with('status', $message);
    }
}
