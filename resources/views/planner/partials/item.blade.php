@php
    $place = $item->place;
    $label = 'D'.$n.'·'.($index + 1);
    $hoursOk = $place->opening_hours_status === \App\Enums\VerificationStatus::Verified && $place->opening_hours;
    $unavailable = ! $place->isPublished();
    $base = ['plan' => $plan->id, 'item' => $item->id];
@endphp

<li
    id="plan-item-{{ $item->id }}"
    tabindex="-1"
    data-item-id="{{ $item->id }}"
    draggable="true"
    x-on:dragstart="start($event, {{ $item->id }})"
    class="grid grid-cols-[3.25rem_1fr] gap-x-3 py-4 outline-none focus-visible:outline-2 focus-visible:outline-offset-2"
>
    <span class="archive-pin mt-0.5 justify-self-start" aria-hidden="true">{{ $label }}</span>

    <div class="min-w-0">
        <h3 class="font-display text-xl font-bold leading-snug">
            <span class="sr-only">{{ $label }}, </span>
            @if ($unavailable)
                {{ $place->name }}
            @else
                <a class="hover:text-rust" href="{{ route('places.show', $place->slug) }}">{{ $place->name }}</a>
            @endif
        </h3>
        <p class="typewriter mt-0.5">{{ $place->area_label ? $place->area_label.', ' : '' }}{{ $place->region?->name }}{{ $place->categories->isNotEmpty() ? ', '.$place->categories->take(2)->pluck('name')->join(', ') : '' }}</p>

        @if ($unavailable)
            <p class="mt-2 text-sm font-semibold text-oxblood">Tempat ini tidak lagi terbit di arsip. Ganti atau hapus dari rencana.</p>
        @endif

        @if ($item->recommendation_reason)
            <p class="mt-2 text-[0.9375rem] text-ink-soft">{{ $item->recommendation_reason }}</p>
        @endif

        <p class="mt-2 text-sm">
            @if ($item->start_time)<span class="font-semibold">Mulai {{ substr((string) $item->start_time, 0, 5) }}.</span>@endif
            @if ($item->duration_minutes)Perkiraan {{ $item->duration_minutes }} menit di sini. @endif
            @unless ($hoursOk)<span class="text-ink-soft">Cek jam buka sebelum berkunjung.</span>@endunless
        </p>

        @if ($item->note)
            <p class="mt-2 whitespace-pre-line border-l-2 border-brass/60 pl-3 text-[0.9375rem]">{{ $item->note }}</p>
        @endif

        <div class="mt-2 flex flex-wrap items-center gap-x-3 print:hidden">
            <form method="POST" action="{{ route('planner.plans.items.move', $base) }}">
                @csrf
                <input type="hidden" name="direction" value="up">
                <button type="submit" class="btn btn-link px-1 text-sm" @disabled($index === 0) aria-label="Naikkan {{ $place->name }}">Naik</button>
            </form>
            <form method="POST" action="{{ route('planner.plans.items.move', $base) }}">
                @csrf
                <input type="hidden" name="direction" value="down">
                <button type="submit" class="btn btn-link px-1 text-sm" @disabled($index === $count - 1) aria-label="Turunkan {{ $place->name }}">Turun</button>
            </form>
            <form method="POST" action="{{ route('planner.plans.items.destroy', $base) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-link px-1 text-sm text-oxblood" aria-label="Hapus {{ $place->name }} dari hari {{ $n }}">Hapus</button>
            </form>
        </div>

        <details class="mt-1 print:hidden">
            <summary class="flex min-h-11 cursor-pointer items-center text-sm font-semibold">Ubah tempat ini</summary>
            <div class="space-y-5 border-l-2 border-ink/20 pb-2 pl-4 pt-2">
                <form method="POST" action="{{ route('planner.plans.items.update', $base) }}" class="space-y-4" x-data="itemNoteForm" x-on:submit.prevent="save($el)">
                    @csrf
                    @method('PATCH')
                    <div class="field">
                        <label for="catatan-{{ $item->id }}" class="field-label">Catatan pribadi <span class="field-optional">(opsional)</span></label>
                        <textarea id="catatan-{{ $item->id }}" name="note" rows="2" maxlength="1000" class="field-input">{{ $item->note }}</textarea>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field name="duration_minutes" :id="'durasi-'.$item->id" label="Durasi kunjungan (menit)" type="number" min="5" max="720" inputmode="numeric" :value="$item->duration_minutes" />
                        <x-field name="start_time" :id="'mulai-'.$item->id" label="Jam mulai" type="time" :value="$item->start_time ? substr((string) $item->start_time, 0, 5) : null" />
                    </div>
                    <div class="flex items-center gap-4">
                        <button type="submit" class="btn btn-secondary" x-bind:aria-busy="(state === 'saving').toString()">Simpan catatan</button>
                        <span class="text-sm font-semibold" role="status" x-text="message" x-bind:class="state === 'error' ? 'text-oxblood' : 'text-moss'"></span>
                    </div>
                </form>

                @if ($plan->days->count() > 1)
                    <form method="POST" action="{{ route('planner.plans.items.move', $base) }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <div class="field">
                            <label for="pindah-{{ $item->id }}" class="field-label">Pindah ke hari</label>
                            <select id="pindah-{{ $item->id }}" name="day_id" class="field-input w-auto">
                                @foreach ($plan->days as $target)
                                    @if ($target->id !== $day->id)
                                        <option value="{{ $target->id }}">Hari {{ $target->day_number }}{{ $target->items->count() >= $maxItems ? ' (penuh)' : '' }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-secondary">Pindahkan</button>
                    </form>
                @endif

                @if ($alts !== [])
                    <div>
                        <p class="field-label">Ganti dengan cadangan</p>
                        <ul class="mt-1">
                            @foreach (array_slice($alts, 0, 3) as $alt)
                                <li>
                                    <form method="POST" action="{{ route('planner.plans.items.replace', $base) }}">
                                        @csrf
                                        <input type="hidden" name="place_id" value="{{ $alt['candidate']->id }}">
                                        <button type="submit" class="btn btn-link px-0 text-left">Ganti dengan {{ $alt['candidate']->name }}</button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </details>
    </div>
</li>
