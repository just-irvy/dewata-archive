<?php

namespace App\Http\Controllers\Site\Planner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planner\DayUpdateRequest;
use App\Models\TripPlan;
use App\Models\TripPlanDay;
use App\Planner\GuestDrafts;
use App\Planner\ItineraryEditor;
use App\Planner\PlanAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DayController extends Controller
{
    public function __construct(
        private readonly PlanAccess $access,
        private readonly ItineraryEditor $editor,
        private readonly GuestDrafts $drafts,
    ) {}

    public function store(Request $request, int $plan): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);
        $day = $this->editor->addDay($model);
        $this->drafts->touch($model);

        return $this->back($model, $day->day_number, 'Hari '.$day->day_number.' ditambahkan.');
    }

    public function update(DayUpdateRequest $request, int $plan, int $day): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);
        $record = $this->day($model, $day);
        $this->editor->updateDay($record, $request->dayData());
        $this->drafts->touch($model);

        return $this->back($model, $record->day_number, 'Hari '.$record->day_number.' diperbarui.');
    }

    public function destroy(Request $request, int $plan, int $day): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);
        $record = $this->day($model, $day);
        $number = $record->day_number;
        $this->editor->removeDay($model, $record);
        $this->drafts->touch($model);

        return $this->back($model, max(1, $number - 1), 'Hari '.$number.' dihapus. Nomor hari berikutnya bergeser.');
    }

    public function regenerate(Request $request, int $plan, int $day): RedirectResponse
    {
        $model = $this->access->resolve($request, $plan);
        $record = $this->day($model, $day);
        $count = $this->editor->regenerateDay($model, $record);
        $this->drafts->touch($model);

        $message = $count > 0
            ? 'Hari '.$record->day_number.' disusun ulang dengan '.$count.' tempat.'
            : 'Tidak ada tempat lain yang cocok untuk Hari '.$record->day_number.'. Hari ini dikosongkan; tambahkan tempat sendiri atau ubah preferensi.';

        return $this->back($model, $record->day_number, $message);
    }

    /** Hari selalu dicari lewat rencana yang sudah lolos akses. ID hari lain menghasilkan 404. */
    private function day(TripPlan $plan, int $id): TripPlanDay
    {
        return $plan->days()->whereKey($id)->firstOrFail();
    }

    private function back(TripPlan $plan, int $dayNumber, string $message): RedirectResponse
    {
        return redirect(PlanAccess::editorUrl($plan, ['hari' => $dayNumber]).'#hari-'.$dayNumber)->with('status', $message);
    }
}
