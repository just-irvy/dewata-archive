{{-- Stay matches (FILE 09): terpisah dari itinerary dan tidak dijumlahkan menjadi biaya perjalanan. --}}
<section aria-labelledby="judul-penginapan" class="border-t-[3px] border-double border-ink pt-8">
    <div class="grid gap-8 lg:grid-cols-12">
        <div class="lg:col-span-4">
            <h2 id="judul-penginapan" class="text-3xl">Penginapan yang cocok</h2>
            <p class="mt-3 text-sm text-ink-soft">Dicocokkan dengan wilayah, jenis, dan kisaran harga per malam yang Anda pilih. Harga menyebut jenis sumber dan tanggal periksanya. Situs ini tidak menampilkan ketersediaan kamar dan tidak menerima pemesanan.</p>
        </div>
        <div class="lg:col-span-8">
            @forelse ($stayMatches as $hotel)
                <div>
                    <x-hotel-row :hotel="$hotel" />
                    @if ($hotel->lat && $hotel->lng)
                        <form method="POST" action="{{ route('planner.plans.hotel', $plan->id) }}" class="-mt-3 pb-4 print:hidden">
                            @csrf
                            <input type="hidden" name="hotel_id" value="{{ $hotel->id }}">
                            <button type="submit" class="btn btn-link px-0">Pakai sebagai titik awal semua hari</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="border-t-2 border-ink pt-4 font-semibold">Belum ada penginapan terbit yang cocok dengan preferensi ini.</p>
                <p class="mt-1 text-sm text-ink-soft">Penginapan tanpa data harga tidak ikut saat kisaran harga diisi. <a class="btn btn-link px-0" href="{{ route('hotels.index') }}">Lihat semua penginapan</a></p>
            @endforelse
        </div>
    </div>
</section>
