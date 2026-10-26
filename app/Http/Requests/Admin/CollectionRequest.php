<?php

namespace App\Http\Requests\Admin;

use App\Models\Collection;
use App\Models\Media;
use App\Models\Place;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CollectionRequest extends AdminRequest
{
    private function collection(): ?Collection
    {
        $collection = $this->route('collection');

        return $collection instanceof Collection ? $collection : null;
    }

    protected function prepareForValidation(): void
    {
        $slug = Str::slug((string) ($this->filled('slug') ? $this->input('slug') : $this->input('title')));

        if ($this->collection()?->status->value === 'published') {
            $slug = $this->collection()->slug;
        }

        $this->merge(['slug' => $slug]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:160', Rule::unique('collections', 'slug')->ignore($this->collection()?->id)],
            'dek' => ['nullable', 'string', 'max:300'],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $mediaId = $this->input('cover_media_id');

            if (blank($mediaId) || $validator->errors()->has('cover_media_id')) {
                return;
            }

            $media = Media::query()->find($mediaId);
            $collection = $this->collection();

            $fromCollectionPlace = $media !== null
                && $media->mediable_type === Place::class
                && $collection !== null
                && $collection->places()->whereKey($media->mediable_id)->exists();

            if (! $fromCollectionPlace) {
                $validator->errors()->add('cover_media_id', 'Foto sampul harus berasal dari salah satu tempat di koleksi ini.');
            }
        }];
    }
}
