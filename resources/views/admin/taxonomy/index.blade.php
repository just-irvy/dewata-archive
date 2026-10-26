<x-layouts.admin :title="$config['title']">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-4xl">{{ $config['title'] }}</h1>
        <x-button :href="route($config['route'].'.create')">{{ ucfirst($config['singular']) }} baru</x-button>
    </div>

    <div class="mt-8 overflow-x-auto">
        <table class="w-full min-w-[32rem] text-left">
            <caption class="sr-only">Daftar {{ strtolower($config['title']) }}</caption>
            <thead>
                <tr class="border-b-2 border-ink text-sm">
                    <th scope="col" class="py-2 pr-4">Nama</th>
                    <th scope="col" class="py-2 pr-4">Slug</th>
                    @foreach (array_keys($config['columns']) as $column)
                        <th scope="col" class="py-2 pr-4">{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr class="border-b border-ink/20">
                        <th scope="row" class="py-3 pr-4 font-semibold"><a class="btn btn-link" href="{{ route($config['route'].'.edit', $record->id) }}">{{ $record->name }}</a></th>
                        <td class="py-3 pr-4 typewriter">{{ $record->slug }}</td>
                        @foreach ($config['columns'] as $resolver)
                            <td class="py-3 pr-4 text-sm">{{ $resolver($record) }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ 2 + count($config['columns']) }}" class="py-6 text-ink-soft">Belum ada data. Tambahkan {{ $config['singular'] }} pertama.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.admin>
