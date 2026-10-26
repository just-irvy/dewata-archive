<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Actions\SaveRecord;
use App\Http\Requests\Admin\RegionRequest;
use App\Models\Region;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class RegionController extends TaxonomyController
{
    protected function model(): string
    {
        return Region::class;
    }

    protected function config(): array
    {
        return [
            'title' => 'Wilayah',
            'singular' => 'wilayah',
            'route' => 'admin.regions',
            'fields' => 'admin.regions._fields',
            'columns' => [
                'Jenis' => fn (Region $r) => $r->region_type->value === 'administrative' ? 'Administratif' : 'Area editorial',
                'Tempat' => fn (Region $r) => $r->places()->count(),
                'Hotel' => fn (Region $r) => $r->hotels()->count(),
                'Status' => fn (Region $r) => $r->is_published ? 'Terbit' : 'Draf',
            ],
        ];
    }

    protected function deleteBlockedReason(Model $record): ?string
    {
        return $record->places()->withTrashed()->exists() || $record->hotels()->withTrashed()->exists()
            ? 'Wilayah masih punya tempat atau hotel. Pindahkan dulu.'
            : null;
    }

    public function store(RegionRequest $request, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('create', Region::class);
        $save(new Region, $request->validated(), $request->user());

        return redirect()->route('admin.regions.index')->with('status', 'Wilayah disimpan.');
    }

    public function edit(Region $region): View
    {
        return $this->editView($region);
    }

    public function update(RegionRequest $request, Region $region, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('update', $region);
        $save($region, $request->validated(), $request->user());

        return redirect()->route('admin.regions.index')->with('status', 'Perubahan disimpan.');
    }

    public function destroy(Region $region, RecordAudit $audit): RedirectResponse
    {
        return $this->destroyRecord($region, $audit);
    }
}
