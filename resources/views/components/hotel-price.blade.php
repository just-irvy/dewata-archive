@props(['hotel'])

@php
    $format = fn ($amount) => 'Rp'.number_format($amount, 0, ',', '.');
    $low = $hotel->price_min ?? $hotel->price_max;
    $stale = $hotel->verified_at && $hotel->verified_at->lt(now()->subDays(config('archive.review_after_days.hotel_price')));
@endphp

@if ($hotel->hasPrice())
    <div {{ $attributes }}>
        <p class="font-semibold">
            {{ $format($low) }}@if ($hotel->price_max && $hotel->price_max !== $low) sampai {{ $format($hotel->price_max) }}@endif
            <span class="font-normal text-ink-soft">per kamar per malam</span>
        </p>
        <p class="typewriter">
            {{ $hotel->price_basis?->label() }}, {{ $hotel->verified_at ? 'diperiksa '.$hotel->verified_at->translatedFormat('j F Y') : 'tanggal periksa tidak tercatat' }}@if ($hotel->price_status->value !== 'verified'), perlu ditinjau @endif
        </p>
        @if ($stale)
            <p class="mt-1 text-sm font-semibold">Harga sudah lama diperiksa. Cek ulang sebelum menginap.</p>
        @endif
    </div>
@else
    <p {{ $attributes->class('text-ink-soft') }}>Harga tidak tersedia.</p>
@endif
