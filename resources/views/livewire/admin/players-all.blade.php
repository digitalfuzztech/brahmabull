<div>
<div class="flex justify-between items-center">
    <h1 class="text-2xl font-bold text-white mb-6">
        All Players
    </h1>
    @auth
    @if(auth()->user()->hasRole('admin'))
    <a href="{{ route('admin.players') }}"
       class="px-4 py-2 bg-slate-800 rounded-xl">
        ← Back
    </a>
        @endif
    @if(auth()->user()->hasRole('agent'))
        <a href="{{ route('agent.players') }}"
           class="px-4 py-2 bg-slate-800 rounded-xl">
            ← Back
        </a>
        @endif
        @endauth
</div>

    <input
        wire:model.live="search"
        class="w-full bg-slate-800 p-2 rounded-xl text-white mb-4"
        placeholder="Search players..."
    />

    <div class="grid grid-cols-1 mb-4">
    {{-- TABLE --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">

            <div class="overflow-x-auto scrollbar-purple">
        <table class="w-full text-sm text-white">

            <thead class="text-slate-400 border-b border-slate-800">
            <tr>
                <th class="text-left p-3">ID</th>
                <th class="text-left p-3">Name</th>
                <th class="text-left p-3">Email</th>
                <th class="text-left p-3">Username</th>
                <th class="text-left p-3">Phone</th>
                <th class="text-left p-3">Referred By</th>
                <th class="text-right p-3">Action</th>
            </tr>
            </thead>

            <tbody>

            @foreach($this->players as $player)

                <tr class="border-b border-slate-800">

                    <td class="p-2">P{{ $player->playerProfile?->player_id }}</td>
                    <td class="p-2">{{ $player->name }}</td>
                    <td class="p-2">{{ $player->email }}</td>
                    <td class="p-2 text-purple-400">
                        {{ $player->username ?? '-' }}
                    </td>
                    <td class="p-2">{{ $player->phone }}</td>
                    <td class="p-2">{{ $player->referrer?->name ?? '-' }}</td>

                    <td class="p-2 text-right">
                        <button
                            wire:click="openPlayer({{ $player->id }})"
                            class="px-3 py-1 bg-purple-600 rounded-lg"
                        >
                            View Details
                        </button>
                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>
            </div>
    </div>

    </div>
    <div class="mt-6">
        {{ $this->players->links() }}
    </div>

    {{-- MODAL --}}
    @if($selectedPlayer)

        @php $player = $selectedPlayer; @endphp

        <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-[999]">

            <div class="bg-slate-900  max-w-5xl rounded-2xl border border-slate-700 relative">
                <div class="flex justify-end ">
                    <button wire:click="closePlayer"
                            class="text-white  p-2 m-2 bg-red-600 rounded-tr-2xl">
                        ✕
                    </button>
                </div>


                {{-- HEADER --}}
                <div class="p-5 border-b border-slate-800">

                    <h2 class="text-white text-xl font-bold">
                        {{ $player->name }}

                        @if($player->username)
                            <span class="text-purple-400 text-base">
            ({{ $player->username }})
        </span>
                        @endif
                    </h2>

                    <p class="text-slate-400">
                        P{{ $player->playerProfile?->player_id }}
                    </p>

                    <p class="text-slate-400">
                        {{ $player->email }}
                    </p>

                </div>

                {{-- BODY --}}
                <div class="p-5 space-y-5 text-white">

                    {{-- GAME ACCOUNTS --}}
                    <div class="max-h-[350px] overflow-y-auto rounded-xl custom-scrollbar">
                    <table class="w-full text-sm mb-8">

                        <thead class="text-slate-400">
                        <tr>
                            <th class="text-left">Game</th>
                            <th class="text-left">Username</th>
                            <th class="text-left">Password</th>
                        </tr>
                        </thead>

                        <tbody>

                        @foreach($player->gameAccounts as $acc)

                            <tr class="border-t border-slate-800">
                                <td>{{ $acc->game->name }}</td>
                                <td>{{ $acc->game_username }}</td>
                                <td>{{ $acc->game_password }}</td>
                            </tr>

                        @endforeach

                        </tbody>

                    </table>
                    </div>
                    {{-- STATS --}}
                    <div class="grid grid-cols-2 gap-3">

                        <div>Total Deposits: {{ $player->deposits->count() }}</div>

                        <div>Total Deposit Amount: {{ $player->deposits->sum('amount') }}</div>

                        <div>Total Withdrawals: {{ $player->cashouts->count() }}</div>

                        <div>Total Withdrawal Amount: {{ $player->cashouts->sum('amount') }}</div>

                        <div>
                            Game Points Used:
                            {{ $player->deposits->sum('game_points_loaded') }}
                        </div>

                        <div>
                            Game Points Redeemed:
                            {{ $player->cashouts->sum('amount') }}
                        </div>

                    </div>

                </div>

            </div>

        </div>

    @endif

</div>
