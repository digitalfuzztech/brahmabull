<div>
<div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-white mb-6">
        Top Players
    </h1>
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
</div>
    {{-- FILTERS --}}
    <div class="flex gap-3 mb-6">

        <input

            wire:model.live="search"

            class="w-full bg-slate-800 text-white p-2 rounded-xl"

            placeholder="Search Player ID, Name or Username"
        >

        <select

            wire:model.live="gameFilter"

            class="bg-slate-800 text-white p-2 rounded-xl"
        >

            <option value="">
                All Games
            </option>

            @foreach($this->games as $game)

                <option value="{{ $game->id }}">
                    {{ $game->name }}
                </option>

            @endforeach

        </select>

    </div>

    {{-- TOP 10 --}}
    <div class="bg-slate-900 rounded-2xl border border-slate-800 p-5 mb-8">

        <h2 class="text-white font-bold mb-4">
            Top 10 Players
        </h2>

        <table class="w-full text-sm text-white">

            <thead class="text-slate-400">

            <tr>

                <th class="text-left p-2">
                    Rank
                </th>

                <th class="text-left">
                    Player ID
                </th>

                <th class="text-left">
                    Player
                </th>

                <th class="text-left">
                    Deposits
                </th>

                <th class="text-left">
                    Total Deposit
                </th>

                <th class="text-left">
                    Points Used
                </th>

            </tr>

            </thead>

            <tbody>

            @foreach($this->topTen as $i => $row)

                <tr class="border-t border-slate-800">

                    <td class="p-2">
                        #{{ $i + 1 }}
                    </td>
                    <td>
                        P{{ $row->player->playerProfile?->player_id }}
                    </td>

                    <td>
                        {{ $row->player->name }}
                    </td>

                    <td>
                        {{ $row->deposit_count }}
                    </td>

                    <td>
                        {{ number_format($row->deposit_total,2) }}
                    </td>

                    <td>
                        {{ number_format($row->points_used,2) }}
                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>

    </div>


    {{-- FULL RANKINGS --}}
    <div class="bg-slate-900 rounded-2xl border border-slate-800 overflow-hidden">

        <div class="max-h-[1200px] overflow-y-auto">
        <table class="w-full text-sm text-white">

            <thead class="text-slate-400 border-b border-slate-800">

            <tr>

                <th class="text-left p-2">S.N.</th>

                <th class="text-left p-2">Player ID</th>

                <th class="text-left p-2">Game Username</th>

                <th class="text-left p-2">Player Name</th>

                <th class="text-left p-2">Points Used</th>

                <th class="text-left p-2">Game</th>

                <th class="text-left p-2">Deposits</th>

                <th class="text-left p-2">Withdrawals</th>

                <th class="text-left p-2">Total Deposit</th>

                <th class="text-left p-2">Total Withdrawal</th>

                <th class="text-left p-2">Net</th>

            </tr>

            </thead>

            <tbody>

            @foreach($this->rankings as $i => $row)

                <tr class="border-b border-slate-800">

                    <td class="p-3">
                        {{ $i + 1 }}
                    </td>

                    <td class="p-3">
                        P{{ $row->account->user->playerProfile?->player_id }}
                    </td>

                    <td class="p-3">
                        {{ $row->account->game_username }}
                    </td>

                    <td class="p-3">
                        {{ $row->account->user->name }}
                    </td>

                    <td class="p-3">
                        {{ number_format($row->points_used,2) }}
                    </td>

                    <td class="p-3">
                        {{ $row->account->game->name }}
                    </td>

                    <td class="p-3">
                        {{ $row->deposit_count }}
                    </td>

                    <td class="p-3">
                        {{ $row->withdraw_count }}
                    </td>

                    <td class="p-3">
                        {{ number_format($row->deposit_total,2) }}
                    </td>

                    <td class="p-3">
                        {{ number_format($row->withdraw_total,2) }}
                    </td>

                    <td class="p-3">
                        {{ number_format($row->net,2) }}
                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>

    </div>
    </div>
</div>
