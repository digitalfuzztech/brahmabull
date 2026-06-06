<div>

    <h1 class="text-2xl font-bold text-white mb-6">
        Players
    </h1>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        @if(auth()->user()->hasRole('admin'))
        {{-- ALL PLAYERS --}}
        <a href="{{ route('admin.players.all') }}"
           class="p-6 bg-slate-900 border border-slate-800 rounded-2xl hover:border-purple-500">

            <h2 class="text-white font-bold text-lg">
                All Players
            </h2>

            <p class="text-slate-400 text-sm mt-2">
                View all players with details, profiles and analytics
            </p>

        </a>
           @elseif(auth()->user()->hasRole('agent'))
            <a href="{{ route('agent.players.all') }}"
               class="p-6 bg-slate-900 border border-slate-800 rounded-2xl hover:border-purple-500">

                <h2 class="text-white font-bold text-lg">
                    All Players
                </h2>

                <p class="text-slate-400 text-sm mt-2">
                    View all players with details, profiles and analytics
                </p>

            </a>
            @endif
        {{-- TOP PLAYERS --}}
        @if(auth()->user()->hasRole('admin'))
            <a wire:navigate
               href="{{ route('admin.players.top') }}"
               class="p-6 bg-slate-900 border border-slate-800 rounded-2xl hover:border-purple-500">

                <h2 class="text-white font-bold text-lg">
                    Top Players
                </h2>

                <p class="text-slate-400 text-sm mt-2">
                    Top 10 ranked players + full leaderboard system
                </p>

            </a>
        @endif

        @if(auth()->user()->hasRole('agent'))
            <a wire:navigate
               href="{{ route('agent.players.top') }}"
               class="p-6 bg-slate-900 border border-slate-800 rounded-2xl hover:border-purple-500">

                <h2 class="text-white font-bold text-lg">
                    Top Players
                </h2>

                <p class="text-slate-400 text-sm mt-2">
                    Top 10 ranked players + full leaderboard system
                </p>

            </a>
        @endif


    </div>

</div>
