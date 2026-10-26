@props(['variant' => 'primary', 'href' => null, 'type' => 'button', 'loading' => false])

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class(['btn', 'btn-'.$variant]) }}>{{ $slot }}</a>
@else
    <button
        type="{{ $type }}"
        {{ $attributes->class(['btn', 'btn-'.$variant]) }}
        @if ($loading) disabled aria-busy="true" @endif
    >{{ $slot }}</button>
@endif
