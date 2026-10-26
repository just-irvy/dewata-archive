@props(['record', 'type'])

@php
    $current = $record->status->value;
    $actions = match ($current) {
        'draft' => [['published', 'Terbitkan', 'primary'], ['archived', 'Arsipkan', 'secondary']],
        'published' => [['draft', 'Kembalikan ke draf', 'secondary'], ['archived', 'Arsipkan', 'secondary']],
        default => [['draft', 'Kembalikan ke draf', 'secondary']],
    };
@endphp

<div class="flex flex-wrap items-center gap-3">
    @foreach ($actions as [$to, $label, $variant])
        <form method="POST" action="{{ route('admin.status', [$type, $record->id]) }}">
            @csrf
            <input type="hidden" name="to" value="{{ $to }}">
            <x-button type="submit" :variant="$variant">{{ $label }}</x-button>
        </form>
    @endforeach
</div>
