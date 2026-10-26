@props(['name', 'label', 'type' => 'text', 'hint' => null, 'value' => null, 'required' => false, 'error' => null, 'hideLabel' => false])

@php
    $dot = \App\Support\FieldName::dot($name);
    $id = $attributes->get('id', \App\Support\FieldName::id($name));
    $error = $error ?: $errors->first($dot);
    $describedBy = collect([$hint ? $id.'-hint' : null, $error ? $id.'-error' : null])->filter()->implode(' ');
    $current = $type === 'password' ? '' : old($dot, $value);
@endphp

<div class="field">
    <label for="{{ $id }}" @class(['field-label', 'sr-only' => $hideLabel])>
        {{ $label }}@if (! $required && ! $hideLabel)<span class="field-optional"> (opsional)</span>@endif
    </label>

    @if ($hint)
        <p id="{{ $id }}-hint" class="field-hint">{{ $hint }}</p>
    @endif

    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $current }}"
        @required($required)
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except('id')->class(['field-input', 'field-input-error' => (bool) $error]) }}
    >

    @if ($error)
        <p id="{{ $id }}-error" class="field-error">
            <svg aria-hidden="true" class="mt-0.5 size-4 shrink-0" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1 .5 14h15L8 1Zm-.75 5h1.5v4h-1.5V6Zm0 5h1.5v1.5h-1.5V11Z"/></svg>
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
