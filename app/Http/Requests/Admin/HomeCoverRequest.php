<?php

namespace App\Http\Requests\Admin;

use App\Models\SiteSetting;

/** Foto sampul beranda. Alt text dan hak pakai wajib, sama seperti syarat terbit foto lain. */
class HomeCoverRequest extends AdminRequest
{
    public function rules(): array
    {
        $hasCover = filled(SiteSetting::value(SiteSetting::HOME_COVER)['path'] ?? null);

        return [
            'file' => [
                $hasCover ? 'nullable' : 'required', 'file', 'image',
                'mimes:'.implode(',', config('archive.media.mimes')),
                'max:'.config('archive.media.max_kb'),
                'dimensions:min_width=1000,max_width=8000,max_height=8000',
            ],
            'alt_text' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
            'credit' => ['nullable', 'required_without:license_note', 'string', 'max:255'],
            'license_note' => ['nullable', 'required_without:credit', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'foto',
            'alt_text' => 'alt text',
            'caption' => 'keterangan',
            'credit' => 'kredit foto',
            'license_note' => 'lisensi',
        ];
    }

    public function messages(): array
    {
        return [
            'file.dimensions' => 'Foto sampul minimal 1000 piksel lebarnya agar tetap tajam di layar besar.',
            'credit.required_without' => 'Isi kredit foto atau lisensi. Foto tanpa keterangan hak pakai tidak ditampilkan.',
            'license_note.required_without' => 'Isi lisensi atau kredit foto.',
        ];
    }
}
