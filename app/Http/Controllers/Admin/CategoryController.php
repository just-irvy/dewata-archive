<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Actions\SaveRecord;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CategoryController extends TaxonomyController
{
    protected function model(): string
    {
        return Category::class;
    }

    protected function config(): array
    {
        return [
            'title' => 'Kategori',
            'singular' => 'kategori',
            'route' => 'admin.categories',
            'fields' => 'admin.categories._fields',
            'columns' => [
                'Tempat' => fn (Category $c) => $c->places()->count(),
                'Status' => fn (Category $c) => $c->is_published ? 'Terbit' : 'Draf',
            ],
        ];
    }

    protected function deleteBlockedReason(Model $record): ?string
    {
        return $record->places()->published()->exists()
            ? 'Kategori dipakai tempat yang terbit. Lepaskan dari tempat itu dulu.'
            : null;
    }

    public function store(CategoryRequest $request, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('create', Category::class);
        $save(new Category, $request->validated(), $request->user());

        return redirect()->route('admin.categories.index')->with('status', 'Kategori disimpan.');
    }

    public function edit(Category $category): View
    {
        return $this->editView($category);
    }

    public function update(CategoryRequest $request, Category $category, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('update', $category);
        $save($category, $request->validated(), $request->user());

        return redirect()->route('admin.categories.index')->with('status', 'Perubahan disimpan.');
    }

    public function destroy(Category $category, RecordAudit $audit): RedirectResponse
    {
        return $this->destroyRecord($category, $audit);
    }
}
