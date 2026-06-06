<div>
    <div class="flex justify-between items-center mb-6">

        <div>
            <h1 class="text-2xl font-bold text-white">
                {{ $game->name }}
            </h1>

            <p class="text-slate-400 text-sm">
                Game Details & Analytics
            </p>
        </div>

        <a href="{{ route('admin.games') }}"
           class="px-4 py-2 bg-slate-800 rounded-xl">
            ← Back
        </a>

    </div>
    <div class="p-5 mb-6 bg-slate-900 border border-slate-800 rounded-2xl">

        <h2 class="text-lg font-bold mb-4">Edit Game {{$game->name}}</h2>

        <div class="space-y-4">
            @if (session()->has('success'))
                <div
                    x-data="{ show: true }"
                    x-init="setTimeout(() => show = false, 5000)"
                    x-show="show"
                    class="mb-4 p-3 bg-green-600/20 text-green-300 rounded-xl border border-green-500"
                >
                    {{ session('success') }}
                </div>
            @endif
            <div class="mb-4">

                @if($game->image)
                    <img
                        src="{{ asset('storage/' . $game->image) }}"
                        class="w-32 h-32 mt-2 rounded-xl object-cover border border-slate-700"
                    >
                @endif
            </div>
                <div class="flex gap-3 items-center">
                    <input wire:model="name"
                           class="w-full bg-slate-800 p-2 rounded-xl text-white"
                           placeholder="Game Name">

                    <input wire:model="game_url"
                           class="w-full bg-slate-800 p-2 rounded-xl text-white"
                           placeholder="Game URL">
                </div>
                <div class="w-full md:w-1/4">
                    <label
                        for="edit_game_image"
                        class="group mt-3 flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-4 transition hover:border-indigo-500 hover:bg-slate-800"
                    >

                        @if($image)

                            <img
                                src="{{ $image->temporaryUrl() }}"
                                class="w-20 h-20 rounded-xl object-cover border-2 border-indigo-500"
                            >

                            <span class="mt-2 text-indigo-300 text-sm">
            Click to change image
        </span>

                        @else

                            <span class="text-slate-300 text-sm">
            Click to upload new image
        </span>

                            <span class="text-xs text-slate-500 mt-1">
            Current image will remain if not changed
        </span>

                        @endif

                        <input
                            id="edit_game_image"
                            type="file"
                            wire:model="image"
                            class="hidden"
                            accept="image/*"
                        />

                    </label>

                    <div wire:loading wire:target="image" class="mt-2 text-sm text-indigo-400">
                        Uploading image...
                    </div>
                </div>

            <button wire:click="updateGame"
                    class="px-4 py-2 bg-purple-600 rounded-xl">
                Save Changes
            </button>

        </div>

    </div>
    <div class="grid grid-cols-3 gap-4 mb-6">

        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl">
            <p class="text-slate-400 text-sm">Total Deposits</p>
            <p class="text-xl font-bold text-white">
                {{ $this->deposits->count() }}
            </p>
        </div>

        <div class="p-4 bg-slate-900 border border-slate-800 rounded-xl">
            <p class="text-slate-400 text-sm">Total Cashouts</p>
            <p class="text-xl font-bold text-white">
                {{ $this->cashouts->count() }}
            </p>
        </div>

    </div>
    <div class="mb-8">

        <h2 class="text-lg font-bold mb-3">Top Players</h2>

        <table class="w-full text-sm text-left">

            <thead class="text-slate-400 border-b border-slate-800">
            <tr>
                <th class="py-2">Rank</th>
                <th>Player</th>
                <th>Deposits</th>
                <th>Total Amount</th>
            </tr>
            </thead>

            <tbody>

            @foreach($this->topPlayers as $index => $player)

                <tr class="border-b border-slate-800 text-white">

                    <td class="py-2">
                        #{{ $index + 1 }}
                    </td>

                    <td>
                        {{ \App\Models\User::find($player->user_id)->name ?? 'Unknown' }}
                    </td>

                    <td>
                        {{ $player->total_deposits }}
                    </td>

                    <td>
                        {{ $player->total_amount }}
                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>

    </div>
    <div class="mb-8">

        <h2 class="text-lg font-bold mb-3">Deposits</h2>

        <div class="space-y-2">

            @foreach($this->deposits as $deposit)

                <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl flex justify-between">

                    <div>
                        <p class="text-white font-bold">
                            {{ $deposit->reference ?? 'Deposit' }}
                        </p>

                        <p class="text-xs text-slate-400">
                            {{ $deposit->amount }}
                        </p>
                    </div>

                    <span class="text-purple-400">
                    {{ $deposit->status }}
                </span>

                </div>

            @endforeach

        </div>

    </div>
    <div>

        <h2 class="text-lg font-bold mb-3">Cashouts</h2>

        <div class="space-y-2">

            @foreach($this->cashouts as $cashout)

                <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl flex justify-between">

                    <div>
                        <p class="text-white font-bold">
                            {{ $cashout->reference }}
                        </p>

                        <p class="text-xs text-slate-400">
                            {{ $cashout->amount }}
                        </p>
                    </div>

                    <span class="text-green-400">
                    {{ $cashout->status }}
                </span>

                </div>

            @endforeach

        </div>

    </div>
</div>
