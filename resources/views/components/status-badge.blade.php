@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $label = ['draft' => 'Draf', 'published' => 'Terbit', 'archived' => 'Diarsipkan'][$value] ?? $value;
@endphp

{{-- Dibedakan oleh gaya garis dan teks, bukan hanya warna. --}}
<span @class([
    'inline-flex min-h-7 items-center px-2 text-xs font-bold',
    'border border-dashed border-ink text-ink' => $value === 'draft',
    'border border-ink bg-ink text-paper' => $value === 'published',
    'border border-dotted border-ink-soft text-ink-soft line-through decoration-1' => $value === 'archived',
])>{{ $label }}</span>
