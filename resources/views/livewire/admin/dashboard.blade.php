<div>

    <h1 class="text-3xl font-black mb-8">
        Admin Dashboard
    </h1>

    <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 gap-6">

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <p class="text-slate-400">Players</p>
            <p class="text-4xl font-black mt-2">
                {{ $playersCount }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <p class="text-slate-400">Agents</p>
            <p class="text-4xl font-black mt-2">
                {{ $agentsCount }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <p class="text-slate-400">Pending Deposits</p>
            <p class="text-4xl font-black mt-2">
                {{ $pendingDeposits }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <p class="text-slate-400">Pending Cashouts</p>
            <p class="text-4xl font-black mt-2">
                {{ $pendingCashouts }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <p class="text-slate-400">Games</p>
            <p class="text-4xl font-black mt-2">
                {{ $gamesCount }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
            <p class="text-slate-400">Wallets</p>
            <p class="text-4xl font-black mt-2">
                {{ $walletsCount }}
            </p>
        </div>

    </div>

    {{-- GAMES SLIDER --}}

    <div class="mt-10">

        <h2 class="text-xl font-bold mb-5">
            Games
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 overflow-x-auto pb-2 scrollbar-purple">
<div class="flex gap-4">
    @foreach($games as $game)
        @if(auth()->user()->hasRole('admin'))
        <a
            href="{{ route('admin.games.show',$game->id) }}"
            class="min-w-[280px] bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden hover:border-purple-500 transition"
        >

            <img
                src="{{ asset('storage/'.$game->image) }}"
                class="w-full h-40 object-cover"
            >

            <div class="p-4">

                <h3 class="font-bold text-white">
                    {{ $game->name }}
                </h3>

            </div>

        </a>
@endif
            @if(auth()->user()->hasRole('agent'))
                <a
                    href="{{ route('agent.games')}}"
                    class="min-w-[280px] bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden hover:border-purple-500 transition"
                >

                    <img
                        src="{{ asset('storage/'.$game->image) }}"
                        class="w-full h-40 object-cover"
                    >

                    <div class="p-4">

                        <h3 class="font-bold text-white">
                            {{ $game->name }}
                        </h3>

                    </div>

                </a>
                @endif
    @endforeach
</div>


        </div>

    </div>

</div>
