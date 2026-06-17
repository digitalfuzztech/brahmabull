@if ($paginator->hasPages())
    <nav class="flex items-center justify-center mt-6">

        <ul class="flex items-center space-x-2">

            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li>
                <span class="px-4 py-2 text-gray-600 bg-black/40 border border-gray-700 rounded-lg cursor-not-allowed">
                    ‹
                </span>
                </li>
            @else
                <li>
                    <a href="{{ $paginator->previousPageUrl() }}"
                       class="px-4 py-2 text-cyan-300 bg-black/40 border border-cyan-500 rounded-lg
                          hover:bg-cyan-500/20 hover:shadow-[0_0_10px_#00ffff]
                          transition-all duration-200">
                        ‹
                    </a>
                </li>
            @endif

            {{-- Pages --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" --}}
                @if (is_string($element))
                    <li>
                        <span class="px-4 py-2 text-gray-500">...</span>
                    </li>
                @endif

                {{-- Array Of Links --}}
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
                                      hover:bg-fuchsia-500/20 hover:text-fuchsia-300
                                      hover:shadow-[0_0_12px_#ff00ff]
                                      transition-all duration-200">
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
                          hover:bg-fuchsia-500/20 hover:shadow-[0_0_10px_#ff00ff]
                          transition-all duration-200">
                        ›
                    </a>
                </li>
            @else
                <li>
                <span class="px-4 py-2 text-gray-600 bg-black/40 border border-gray-700 rounded-lg cursor-not-allowed">
                    ›
                </span>
                </li>
            @endif

        </ul>
    </nav>
@endif
