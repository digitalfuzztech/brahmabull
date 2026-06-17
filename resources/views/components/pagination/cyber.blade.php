@if ($paginator->hasPages())
    <nav class="flex justify-center mt-6">

        <ul class="flex items-center space-x-2">

            {{-- Prev --}}
            @if ($paginator->onFirstPage())
                <li>
                <span class="px-4 py-2 text-gray-600 bg-black/40 border border-gray-700 rounded-lg">
                    ‹
                </span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}"
                       class="px-4 py-2 text-cyan-300 bg-black/40 border border-cyan-500 rounded-lg
                          hover:shadow-[0_0_12px_#00ffff]
                          hover:bg-cyan-500/20 transition">
                        ‹
                    </a>
                </li>
            @endif

            {{-- Pages --}}
            @foreach ($elements as $element)

                @if (is_string($element))
                    <li class="px-3 text-gray-500">...</li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)

                        @if ($page == $paginator->currentPage())
                            <li>
                            <span class="px-4 py-2 font-bold text-black
                                         bg-gradient-to-r from-fuchsia-500 to-cyan-400
                                         rounded-lg shadow-[0_0_15px_#ff00ff]
                                         border border-fuchsia-400">
                                {{ $page }}
                            </span>
                            </li>
                        @else
                            <li>
                                <a href="{{ $url }}"
                                   class="px-4 py-2 text-cyan-300 bg-black/40 border border-cyan-700 rounded-lg
                                      hover:text-fuchsia-300 hover:shadow-[0_0_12px_#ff00ff]
                                      transition">
                                    {{ $page }}
                                </a>
                            </li>
                        @endif

                    @endforeach
                @endif

            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li>
                    <a href="{{ $paginator->nextPageUrl() }}"
                       class="px-4 py-2 text-fuchsia-300 bg-black/40 border border-fuchsia-500 rounded-lg
                          hover:shadow-[0_0_12px_#ff00ff]
                          hover:bg-fuchsia-500/20 transition">
                        ›
                    </a>
                </li>
            @else
                <li>
                <span class="px-4 py-2 text-gray-600 bg-black/40 border border-gray-700 rounded-lg">
                    ›
                </span>
                </li>
            @endif

        </ul>
    </nav>
@endif
