<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/** Dasar untuk wilayah, kategori, dan tag. Subclass menyediakan store dan update dengan Form Request sendiri. */
abstract class TaxonomyController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    /** @return array{title: string, singular: string, route: string, fields: string, columns: array<string, callable>} */
    abstract protected function config(): array;

    abstract protected function deleteBlockedReason(Model $record): ?string;

    public function index(): View
    {
        Gate::authorize('viewAny', $this->model());

        return view('admin.taxonomy.index', [
            'config' => $this->config(),
            'records' => $this->model()::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', $this->model());

        return view('admin.taxonomy.form', ['config' => $this->config(), 'record' => new ($this->model())]);
    }

    protected function editView(Model $record): View
    {
        Gate::authorize('update', $record);

        return view('admin.taxonomy.form', ['config' => $this->config(), 'record' => $record]);
    }

    protected function destroyRecord(Model $record, RecordAudit $audit): RedirectResponse
    {
        Gate::authorize('delete', $record);

        $config = $this->config();

        if ($reason = $this->deleteBlockedReason($record)) {
            return back()->withErrors(['delete' => $reason]);
        }

        $audit(Str::snake(class_basename($record)).'.deleted', $record, ['name' => $record->name, 'slug' => $record->slug], null);
        $record->delete();

        return redirect()->route($config['route'].'.index')->with('status', ucfirst($config['singular']).' dihapus.');
    }
}
