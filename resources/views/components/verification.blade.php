@props(['status' => null, 'at' => null, 'label' => null])

{{-- Label status data yang bisa berubah. Tidak pernah menampilkan angka tanpa status. --}}
<p {{ $attributes->class('typewriter') }}>
    {{ $label ?? __('ui.verification.label') }}:
    <strong class="font-bold text-ink">{{ $status?->label() ?? \App\Enums\VerificationStatus::Unknown->label() }}</strong>,
    @if ($at)
        {{ __('ui.verification.checked') }} <time datetime="{{ $at->toIso8601String() }}">{{ $at->translatedFormat('j F Y') }}</time>
    @else
        {{ __('ui.verification.not_checked') }}
    @endif
</p>
