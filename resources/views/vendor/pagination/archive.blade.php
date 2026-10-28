@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Halaman hasil" class="flex flex-wrap items-center justify-between gap-4 border-t border-ink/20 pt-4">
        <p class="typewriter">Halaman {{ $paginator->currentPage() }}@if ($paginator->lastPage() !== null) dari {{ $paginator->lastPage() }}@endif</p>

        <ul class="flex flex-wrap items-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span class="btn btn-secondary" aria-disabled="true">Sebelumnya</span>
                @else
                    <a class="btn btn-secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
                @endif
            </li>

            @if (isset($elements))
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li aria-hidden="true" class="px-2">{{ $element }}</li>
                    @elseif (is_array($element))
                        @foreach ($element as $page => $url)
                            <li class="hidden sm:block">
                                @if ($page == $paginator->currentPage())
                                    <span class="btn border-ink bg-ink text-paper" aria-current="page"><span class="sr-only">Halaman </span>{{ $page }}</span>
                                @else
                                    <a class="btn btn-link no-underline" href="{{ $url }}"><span class="sr-only">Ke halaman </span>{{ $page }}</a>
                                @endif
                            </li>
                        @endforeach
                    @endif
                @endforeach
            @endif

            <li>
                @if ($paginator->hasMorePages())
                    <a class="btn btn-secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
                @else
                    <span class="btn btn-secondary" aria-disabled="true">Berikutnya</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
