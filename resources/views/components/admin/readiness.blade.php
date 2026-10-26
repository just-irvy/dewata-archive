@props(['report'])

<section aria-labelledby="kesiapan" class="border border-ink/25 bg-surface p-4">
    <h2 id="kesiapan" class="text-lg">Kesiapan terbit</h2>

    @if ($report->passes())
        <p class="mt-2 text-sm font-semibold">Semua syarat terbit terpenuhi.</p>
    @else
        <p class="mt-2 text-sm font-bold text-oxblood">Belum bisa diterbitkan:</p>
        <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
            @foreach ($report->blockers as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif

    @if ($report->warnings !== [])
        <p class="mt-4 text-sm font-bold">Perlu diperhatikan:</p>
        <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-ink-soft">
            @foreach ($report->warnings as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    @endif
</section>
