@props(['name', 'legend', 'options' => [], 'checked' => [], 'hint' => null])

@php
    $selected = session()->hasOldInput() ? array_map('strval', (array) old($name, [])) : array_map('strval', (array) $checked);
@endphp

<fieldset class="field">
    <legend class="field-label">{{ $legend }}</legend>
    @if ($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif
    <div class="mt-1 grid gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($options as $value => $text)
            <label class="flex min-h-11 items-center gap-3 text-[0.9375rem]">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $value }}" class="size-5 accent-rust" @checked(in_array((string) $value, $selected, true))>
                <span>{{ $text }}</span>
            </label>
        @empty
            <p class="text-sm text-ink-soft">Belum ada pilihan.</p>
        @endforelse
    </div>
    @error($name)
        <p class="field-error"><span>{{ $message }}</span></p>
    @enderror
</fieldset>
