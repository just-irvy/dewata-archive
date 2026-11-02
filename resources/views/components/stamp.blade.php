@props(['status' => null])

{{-- Cap verifikasi (DESIGN.md): Terverifikasi bertinta lumut, Perlu ditinjau putus-putus kuningan, Belum diketahui garis tipis. --}}
@php
    $status = $status instanceof \App\Enums\VerificationStatus ? $status : \App\Enums\VerificationStatus::tryFrom((string) $status);
    [$class, $text] = match ($status) {
        \App\Enums\VerificationStatus::Verified => ['stamp-verified', 'Terverifikasi'],
        \App\Enums\VerificationStatus::NeedsReview => ['stamp-review', 'Perlu ditinjau'],
        default => ['stamp-unknown', 'Belum diketahui'],
    };
@endphp

<span {{ $attributes->class(['stamp', $class]) }}>[{{ $text }}]</span>
