@props(['type'])

@php($report = session('bulk_report'))

@if ($report && $report['type'] === $type)
    <section role="status" aria-labelledby="hasil-massal" class="mt-8 border-l-4 border-moss bg-surface px-4 py-3">
        <h2 id="hasil-massal" class="text-lg">Hasil terbit massal</h2>
        <p class="mt-1 text-sm">
            Diterbitkan <strong>{{ $report['published'] }}</strong>.
            Dilewati karena belum memenuhi syarat <strong>{{ $report['blocked'] }}</strong>.
            @if ($report['remaining'] > 0)
                Masih ada <strong>{{ $report['remaining'] }}</strong> draf yang belum diproses karena batas sekali klik. Jalankan lagi.
            @endif
        </p>

        @if ($report['reasons'] !== [])
            <p class="mt-3 text-sm font-semibold">Penyebab yang paling sering:</p>
            <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
                @foreach ($report['reasons'] as $message => $count)
                    <li>{{ $message }} <span class="typewriter">({{ $count }} draf)</span></li>
                @endforeach
            </ul>
        @endif

        @if ($report['samples'] !== [])
            <p class="mt-3 text-sm text-ink-soft">Contoh yang dilewati: {{ implode(', ', $report['samples']) }}.</p>
        @endif
    </section>
@endif
