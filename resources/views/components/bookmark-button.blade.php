@props(['place', 'saved' => false])

@php
    $icon = '<svg aria-hidden="true" class="size-4 shrink-0" viewBox="0 0 16 16" fill="'.($saved ? 'currentColor' : 'none').'" stroke="currentColor" stroke-width="1.5"><path d="M3.5 2h9v12L8 11l-4.5 3V2Z" stroke-linejoin="round"/></svg>';
@endphp

@auth
    <form method="POST" action="{{ route($saved ? 'places.bookmark.destroy' : 'places.bookmark.store', $place->slug) }}" x-data="{ busy: false }" x-on:submit="busy = true" class="inline">
        @csrf
        @method($saved ? 'DELETE' : 'PUT')
        <button type="submit" class="btn btn-link gap-1.5" x-bind:aria-busy="busy.toString()" aria-label="{{ ($saved ? 'Hapus dari tersimpan: ' : 'Simpan: ').$place->name }}">
            {!! $icon !!}<span>{{ $saved ? 'Tersimpan' : 'Simpan' }}</span>
        </button>
    </form>
@else
    <a href="{{ route('places.save', $place->slug) }}" class="btn btn-link gap-1.5" aria-label="Masuk untuk menyimpan {{ $place->name }}">
        {!! $icon !!}<span>Simpan</span>
    </a>
@endauth
