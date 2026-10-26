<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAudit;
use App\Actions\SaveRecord;
use App\Http\Requests\Admin\TagRequest;
use App\Models\Tag;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TagController extends TaxonomyController
{
    protected function model(): string
    {
        return Tag::class;
    }

    protected function config(): array
    {
        return [
            'title' => 'Tag',
            'singular' => 'tag',
            'route' => 'admin.tags',
            'fields' => 'admin.tags._fields',
            'columns' => [
                'Jenis' => fn (Tag $t) => ['theme' => 'Tema', 'attribute' => 'Atribut', 'accessibility' => 'Aksesibilitas'][$t->tag_type->value],
                'Tempat' => fn (Tag $t) => $t->places()->count(),
            ],
        ];
    }

    protected function deleteBlockedReason(Model $record): ?string
    {
        return null;
    }

    public function store(TagRequest $request, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('create', Tag::class);
        $save(new Tag, $request->validated(), $request->user());

        return redirect()->route('admin.tags.index')->with('status', 'Tag disimpan.');
    }

    public function edit(Tag $tag): View
    {
        return $this->editView($tag);
    }

    public function update(TagRequest $request, Tag $tag, SaveRecord $save): RedirectResponse
    {
        Gate::authorize('update', $tag);
        $save($tag, $request->validated(), $request->user());

        return redirect()->route('admin.tags.index')->with('status', 'Perubahan disimpan.');
    }

    public function destroy(Tag $tag, RecordAudit $audit): RedirectResponse
    {
        return $this->destroyRecord($tag, $audit);
    }
}
