<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeStatus;
use App\Enums\PublishStatus;
use App\Exceptions\NotReadyToPublish;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Hotel;
use App\Models\Place;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ContentStatusController extends Controller
{
    private const MODELS = [
        'places' => Place::class,
        'hotels' => Hotel::class,
        'collections' => Collection::class,
    ];

    private const MESSAGES = [
        'published' => 'Diterbitkan.',
        'draft' => 'Dikembalikan ke draf.',
        'archived' => 'Diarsipkan.',
    ];

    public function __invoke(Request $request, string $type, int $id, ChangeStatus $change): RedirectResponse
    {
        $record = self::MODELS[$type]::query()->findOrFail($id);

        Gate::authorize('publish', $record);

        $to = PublishStatus::from($request->validate([
            'to' => ['required', Rule::enum(PublishStatus::class)],
        ])['to']);

        try {
            $change($record, $to);
        } catch (NotReadyToPublish $e) {
            return back()->withErrors(['publish' => $e->report->blockers]);
        }

        return back()->with('status', self::MESSAGES[$to->value]);
    }
}
