@if (session('status'))
    <div role="status" class="mb-8 border-l-4 border-moss bg-surface px-4 py-3 text-sm font-semibold">
        {{ session('status') }}
    </div>
@endif
