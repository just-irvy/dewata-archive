@props(['points' => [], 'total' => 0])

{{--
| Lembar peta Pulau Bali (pengganti foto sampul selama belum dipasang).
| Garis pantai: Natural Earth, domain publik. Titik: tempat terbit di arsip, dari koordinat database.
--}}
@php
    $plate = app(\App\Support\BaliPlate::class);
    $w = $plate->width();
    $h = $plate->height();
    $bar = $plate->scaleBar(20);
    $dots = collect($points)->filter(fn ($p) => $plate->contains($p[0], $p[1]))->map(fn ($p) => $plate->project($p[0], $p[1]));
    [$labelX, $labelY] = $plate->project(-8.33, 115.08);
    $water = [
        ['Laut Bali', ...$plate->project(-7.98, 115.05), 'middle'],
        ['Samudra Hindia', ...$plate->project(-8.93, 115.05), 'middle'],
        ['Selat Bali', ...$plate->project(-8.60, 114.40), 'start'],
    ];
    [$lombokX, $lombokY] = $plate->project(-8.58, 115.825);
@endphp

<figure {{ $attributes->class('bali-plate') }}>
    <div class="border-[3px] border-double border-ink bg-surface p-2">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-labelledby="bali-plate-title bali-plate-desc" class="block h-auto w-full">
            <title id="bali-plate-title">Peta Pulau Bali</title>
            <desc id="bali-plate-desc">Garis pantai Pulau Bali dan Nusa Penida dengan {{ $dots->count() }} titik tempat yang terbit di arsip.</desc>
            <defs>
                <pattern id="bali-plate-water" width="6" height="6" patternUnits="userSpaceOnUse">
                    <path d="M0 3h6" stroke="#B18A50" stroke-width="0.6" opacity="0.35"/>
                </pattern>
                <clipPath id="bali-plate-frame"><rect width="{{ $w }}" height="{{ $h }}"/></clipPath>
            </defs>

            <g clip-path="url(#bali-plate-frame)">
                <rect width="{{ $w }}" height="{{ $h }}" fill="url(#bali-plate-water)"/>

                @foreach ($plate->graticule() as $line)
                    <line x1="{{ $line['x1'] }}" y1="{{ $line['y1'] }}" x2="{{ $line['x2'] }}" y2="{{ $line['y2'] }}" stroke="#202723" stroke-width="0.5" stroke-dasharray="2 4" opacity="0.35"/>
                @endforeach

                {{-- Garis pantai: bayangan tipis lalu daratan bertinta. --}}
                <g transform="translate(2.5 2.5)" fill="#202723" opacity="0.12">
                    @foreach ($plate->coastline() as $d)<path d="{{ $d }}"/>@endforeach
                </g>
                <g fill="#F6F0E4" stroke="#202723" stroke-width="1.6" stroke-linejoin="round">
                    @foreach ($plate->coastline() as $d)<path d="{{ $d }}"/>@endforeach
                </g>

                <text x="{{ $labelX }}" y="{{ $labelY }}" text-anchor="middle" font-family="Playfair Display, Georgia, serif" font-weight="700" font-size="30" letter-spacing="14" fill="#202723" opacity="0.18">BALI</text>

                @foreach ($dots as [$x, $y])
                    <circle cx="{{ $x }}" cy="{{ $y }}" r="3.4" fill="#9C4935" stroke="#F6F0E4" stroke-width="1"/>
                @endforeach

                @foreach ($water as [$name, $x, $y, $anchor])
                    <text x="{{ $x }}" y="{{ $y }}" text-anchor="{{ $anchor }}" font-family="Playfair Display, Georgia, serif" font-style="italic" font-size="16" fill="#4B554F">{{ $name }}</text>
                @endforeach
                <text x="{{ $lombokX }}" y="{{ $lombokY }}" transform="rotate(90 {{ $lombokX }} {{ $lombokY }})" text-anchor="middle" font-family="Playfair Display, Georgia, serif" font-style="italic" font-size="16" fill="#4B554F">Selat Lombok</text>

                @foreach ($plate->graticule() as $line)
                    @if ($line['axis'] === 'lng')
                        <text x="{{ $line['x1'] + 4 }}" y="{{ $h - 7 }}" font-family="Courier Prime, Courier New, monospace" font-size="11" fill="#4B554F">{{ $line['label'] }}</text>
                    @else
                        <text x="5" y="{{ $line['y1'] - 4 }}" font-family="Courier Prime, Courier New, monospace" font-size="11" fill="#4B554F">{{ $line['label'] }}</text>
                    @endif
                @endforeach

                {{-- Mata angin dan skala. --}}
                <g transform="translate({{ $w - 34 }} 38)" fill="#202723">
                    <path d="M0 -22 L7 6 L0 1 L-7 6 Z"/>
                    <text y="24" text-anchor="middle" font-family="Courier Prime, Courier New, monospace" font-size="14" font-weight="700">U</text>
                </g>
                <g transform="translate({{ $w - $bar - 26 }} {{ $h - 34 }})" font-family="Courier Prime, Courier New, monospace" font-size="12" fill="#202723">
                    <rect width="{{ $bar / 2 }}" height="5" fill="#202723"/>
                    <rect x="{{ $bar / 2 }}" width="{{ $bar / 2 }}" height="5" fill="#F6F0E4" stroke="#202723" stroke-width="0.8"/>
                    <text y="-5">0</text>
                    <text x="{{ $bar }}" y="-5" text-anchor="end">20 km</text>
                </g>
            </g>
        </svg>
    </div>
    <figcaption class="typewriter mt-2 flex flex-wrap justify-between gap-x-4 gap-y-1">
        <span><span class="mr-1 inline-block size-2 bg-rust align-middle" aria-hidden="true"></span>{{ number_format($total, 0, ',', '.') }} tempat terbit di arsip</span>
        <span>Garis pantai: Natural Earth</span>
    </figcaption>
</figure>
