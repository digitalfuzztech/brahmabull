<div>

    <!-- HEADER -->
    <div class="flex justify-between items-center mb-6">

        <div>
            <h1 class="text-2xl font-bold text-white">
                {{ $wallet->walletType->agent->name }}

                <span class="text-slate-500">→</span>

                {{ $wallet->walletType->name }}

                <span class="text-slate-500">→</span>

                {{ $wallet->name }}
            </h1>
            <p class="text-sm text-slate-400 mt-2">

                Created By:
                {{ $wallet->creator?->name ?? '-' }}

                <span class="mx-2">|</span>

                Last Updated By:
                {{ $wallet->updater?->name ?? '-' }}

            </p>
            <p class="text-slate-400 text-sm">
                Wallet Details & Analytics
            </p>
        </div>
        @if(auth()->user()->hasRole('admin'))
            <a href="{{ url('/admin/accounts/agent-'.$wallet->wallet_agent_id.'/wallet-type-'.$wallet->wallet_type_id) }}"
               class="px-4 py-2 bg-slate-800 rounded-xl">
                ← Back
            </a>
        @elseif(auth()->user()->hasRole('agent'))
            <a href="{{ url('/agent/accounts/agent-'.$wallet->wallet_agent_id.'/wallet-type-'.$wallet->wallet_type_id) }}"
               class="px-4 py-2 bg-slate-800 rounded-xl">
                ← Back
            </a>
        @endif


    </div>

    <!-- UPDATE WALLET -->
    <div class="p-5 mb-6 bg-slate-900 border border-slate-800 rounded-2xl">

        <h2 class="text-lg font-bold mb-4">Edit Wallet</h2>
        @if(session()->has('success'))

            <div
                x-data="{ show: true }"
                x-init="setTimeout(() => show = false, 5000)"
                x-show="show"
                x-transition
                class="mb-6 rounded-2xl border border-green-500/30 bg-green-500/10 p-4 text-green-300"
            >
                {{ session('success') }}
            </div>

        @endif
        <div class="space-y-4">

            <input
                wire:model="name"
                class="w-full bg-slate-800 p-2 rounded-xl text-white"
                placeholder="Wallet Name"
            />

            <input
                wire:model="account_identifier"
                class="w-full bg-slate-800 p-2 rounded-xl text-white"
                placeholder="Cashtag / Email / Phone"
            />

            <!-- QR UPLOAD (REGISTER STYLE SMALL PREVIEW) -->
            <label
                for="qr"
                class="group flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-6 transition hover:border-indigo-500 hover:bg-slate-800"
            >

                @if($qr_image)

                    <img
                        src="{{ $qr_image->temporaryUrl() }}"
                        class="h-24 rounded-xl border-2 border-indigo-500"
                    >

                    <span class="mt-2 text-indigo-300 text-sm">
                        Click to change QR
                    </span>

                @else

                    @if($wallet->qr_image)

                        <img
                            src="{{ asset('storage/'.$wallet->qr_image) }}"
                            class="h-24 rounded-xl border border-slate-700"
                        >

                        <span class="mt-2 text-indigo-300 text-sm">
                            Click to replace QR
                        </span>

                    @else

                        <svg class="h-10 w-10 text-slate-400"
                             fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>

                        <span class="mt-2 text-slate-300 text-sm">
                            Upload QR Code
                        </span>

                    @endif

                @endif

                <input
                    id="qr"
                    type="file"
                    wire:model="qr_image"
                    class="hidden"
                />

            </label>

            <div wire:loading wire:target="qr_image"
                 class="text-sm text-indigo-400">
                Uploading...
            </div>

            <button
                wire:click="updateWallet"
                wire:loading.attr="disabled"
                wire:target="updateWallet"
                class="px-4 py-2 bg-purple-600 rounded-xl"
            >
                <span wire:loading.remove wire:target="updateWallet">
                    Save Changes
                </span>

                <span wire:loading wire:target="updateWallet">
                    Updating...
                </span>
            </button>

        </div>

    </div>

    <!-- TOP PLAYERS -->
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

                    <td class="py-2">#{{ $index + 1 }}</td>

                    <td>
                        {{ \App\Models\User::find($player->user_id)->name ?? 'Unknown' }}
                    </td>

                    <td>{{ $player->total_deposits }}</td>

                    <td>{{ $player->total_amount }}</td>

                </tr>

            @endforeach

            </tbody>

        </table>

    </div>

    <!-- DEPOSITS -->
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

    <!-- CASHOUTS -->
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
