<div>

    <h1 class="text-2xl font-bold text-white mb-6">
        Deposits
    </h1>
    <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-6">

        <input
            type="date"
            wire:model.live="searchDate"
            class="bg-slate-800 rounded-xl p-2"
        >

        <input
            wire:model.live="search"
            placeholder="Ref / Player / Username"
            class="bg-slate-800 rounded-xl p-2"
        >

        <select
            wire:model.live="gameFilter"
            class="bg-slate-800 rounded-xl p-2"
        >
            <option value="">All Games</option>

            @foreach($this->games as $game)
                <option value="{{ $game->id }}">
                    {{ $game->name }}
                </option>
            @endforeach

        </select>

        <select
            wire:model.live="walletTypeFilter"
            class="bg-slate-800 rounded-xl p-2"
        >
            <option value="">
                All Wallet Types
            </option>

            @foreach($this->walletTypes as $type)
                <option value="{{ $type->id }}">
                    {{ $type->name }}
                </option>
            @endforeach

        </select>

        <select
            wire:model.live="walletFilter"
            class="bg-slate-800 rounded-xl p-2"
        >
            <option value="">
                All Wallets
            </option>

            @foreach($this->wallets as $wallet)
                <option value="{{ $wallet->id }}">
                    {{ $wallet->name }}
                </option>
            @endforeach

        </select>
        <select
            wire:model.live="statusFilter"
            class="bg-slate-800 rounded-xl p-2"
        >
            <option value="">
                All Status
            </option>

            <option value="pending">
                Pending
            </option>

            <option value="verified">
                Verified
            </option>

            <option value="rejected">
                Rejected
            </option>

        </select>
    </div>


    @if (session()->has('success'))
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 5000)"
            x-show="show"
            class="mb-4 p-3 bg-green-600 text-white rounded-xl"
        >
            {{ session('success') }}
        </div>
    @endif

    @foreach($this->deposits as $date => $group)

        <div class="grid grid-cols-1 mb-4">

            <h2 class="text-lg font-bold text-white mb-3">
                {{ $date }}
            </h2>

            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">

                <div class="overflow-x-auto scrollbar-purple relative">

                    <table class="min-w-[2200px] text-sm text-left border-collapse">

                        <thead class="text-slate-400 border-b border-slate-800 bg-slate-950">
                    <tr>

                        <th
                            class="
        px-5
        py-4
        whitespace-nowrap
        sticky
        left-0
        z-40
        bg-slate-950
        shadow-[4px_0_8px_rgba(0,0,0,0.3)]
    "
                        >
                            S.N.
                        </th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Reference No.</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Player</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Username</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Game</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Game Username</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Amount</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Points Loaded</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Bonus</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Agent</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Type</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Wallet</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Proof</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Status</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Original Verifier</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Handled By</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Handled At</th>
                        <th class="px-5 py-4 whitespace-nowrap text-right">Action</th>
                    </tr>
                    </thead>

                    <tbody>

                    @foreach($group as $i => $deposit)

                        @php
                            $isPending = $deposit->status === 'pending';
                        @endphp

                        <tr class="
    border-b border-slate-800 text-white
    hover:bg-gray-400/40 transition
    {{ $isPending ? 'bg-gray-700/60' : 'bg-transparent opacity-80' }}
">


                            <td
                                class="
        px-5
        py-4
        whitespace-nowrap
        sticky
        left-0
        z-30
        bg-slate-900
        shadow-[4px_0_8px_rgba(0,0,0,0.3)]
    "
                            > {{ $i + 1 }}

                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-left"> {{ $deposit->reference }}</td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $deposit->user->name }}</td>
                            <td class="px-5 py-4 whitespace-nowrap text-left text-purple-400">
                                {{ $deposit->user?->username ?? '-' }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $deposit->game->name }}</td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">

                                {{
                                    \App\Models\GameAccount::where(
                                        'user_id',
                                        $deposit->user_id
                                    )
                                    ->where(
                                        'game_id',
                                        $deposit->game_id
                                    )
                                    ->value('game_username')
                                    ?? '-'
                                }}

                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $deposit->amount }}</td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">

                                {{ $deposit->game_points_loaded ?? '-' }}

                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">

                                {{ $deposit->bonus_points_added ?? '-' }}

                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $deposit->wallet?->walletAgent?->name ?? '-' }}</td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $deposit->wallet?->walletType?->name ?? '-' }}</td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $deposit->wallet?->name ?? '-' }}</td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">
                                @if($deposit->proof_image)
                                    <img
                                        src="{{ asset('storage/'.$deposit->proof_image) }}"
                                        class="
        h-14
        w-14
        rounded-xl
        cursor-pointer
        object-cover
        border
        border-slate-700
        hover:scale-105
        transition
    "
                                        wire:click="openProof('{{ $deposit->proof_image }}')"
                                    >
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">
    <span class="{{ $deposit->status === 'verified' ? 'text-green-400' : ($deposit->status === 'rejected' ? 'text-red-400' : 'text-yellow-400') }}">
        {{ $deposit->status ?? 'pending' }}
    </span>
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">
                                {{ $deposit->original_verified_by
                                    ? \App\Models\User::find($deposit->original_verified_by)?->name
                                    : '-' }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">
                                {{ $deposit->verified_by
                                    ? \App\Models\User::find($deposit->verified_by)?->name
                                    : '-' }}
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">
                                {{ $deposit->verified_at ? \Carbon\Carbon::parse($deposit->verified_at)->format('Y-m-d H:i:s') : '-' }}
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-right">
                                @if($deposit->status === 'verified')

                                    <div class="flex gap-2 justify-end">

        <span class="px-3 py-1 bg-green-700 rounded-lg">
            Verified
        </span>

                                        @role('admin')
                                        <button
                                            wire:click="openModal({{ $deposit->id }})"
                                            class="px-3 py-1 bg-yellow-600 rounded-lg"
                                        >
                                            Edit
                                        </button>
                                        @endrole

                                    </div>

                                @else

                                    <button
                                        wire:click="openModal({{ $deposit->id }})"
                                        class="px-3 py-1 bg-purple-600 rounded-lg"
                                    >
                                        Process Deposit
                                    </button>

                                @endif
                            </td>
                        </tr>

                    @endforeach

                    </tbody>

                </table>

            </div>
            </div>
        </div>

    @endforeach
    <div class="mt-6 custom-page-styles">
        {{ $this->deposits->links() }}
    </div>

    {{-- MODAL --}}
    @if($selectedDeposit)

        <div class="bb-admin-modal-overlay fixed inset-0 z-[999] flex items-center justify-center">

            <div class="bb-admin-modal-shell max-w-lg">

                <div class="bb-admin-modal-header flex items-center justify-between border-b border-slate-800 p-5">
                    <h2 class="text-white font-bold">
                        {{ $selectedDeposit->reference }} Processing
                    </h2>
                    <button type="button" wire:click="closeModal" class="bb-admin-modal-close" aria-label="Close deposit processing">×</button>
                </div>

                <div class="bb-admin-modal-body flex-1 overflow-y-auto p-5 space-y-3 min-h-0 custom-scrollbar">

                    <p class="text-white">
                        Player Name: {{ $selectedDeposit->user->name }}
                    </p>

                    <p class="text-white">
                        Player Username: {{ $selectedDeposit->user->username }}
                    </p>

                    <p class="text-white">
                        Game: {{ $selectedDeposit->game->name }}
                    </p>
                    <p class="text-white">
                        Deposit Amount: ${{ $selectedDeposit->amount }}
                    </p>
                    @if($activeVipBadge)
                        <section data-spin-vip-banner class="rounded-xl border-2 border-amber-300 bg-gradient-to-r from-amber-400/20 to-fuchsia-500/20 p-4 shadow-[0_0_24px_rgba(251,191,36,.25)]">
                            <p class="font-black tracking-wider text-amber-200">VIP BADGE ACTIVE</p>
                            <p class="mt-2 text-white">Player currently has: <strong>{{ $activeVipBadge['name'] }}</strong></p>
                            <p class="text-amber-100">Valid until: {{ $activeVipBadge['expires'] ?? 'No expiration' }}</p>
                        </section>
                    @endif
                    <section class="rounded-xl border border-amber-400/30 bg-amber-400/10 p-3 text-sm">
                        <p class="font-bold text-amber-200">Promotional Bonus Reminder</p>
                        <p class="mt-2 text-white">Requested / Deposit Amount: {{ number_format((float) $selectedDeposit->amount, 2) }}</p>
                        <p class="text-white">Pending Promotional Bonus: {{ number_format($pendingPromotionalBonus) }}</p>
                        <p class="text-amber-100">Promotional Total Reference: {{ number_format((float) $selectedDeposit->amount + $pendingPromotionalBonus, 2) }}</p>
                        <p class="mt-1 text-xs text-slate-400">Informational only. This does not alter the deposit or loaded amount.</p>
                        @error('bonus') <p class="mt-2 text-red-300">{{ $message }}</p> @enderror
                    </section>
                    <p class="text-slate-400 text-sm">
                        {{ $selectedDeposit->game->game_url }}
                    </p>
                    <div class="flex items-center gap-3">
                        @if($selectedDeposit?->proof_image)
                            <img
                                src="{{ asset('storage/'.$selectedDeposit->proof_image) }}"
                                class="w-10 h-10 rounded-lg object-cover cursor-pointer"
                                wire:click="openProof('{{ $selectedDeposit->proof_image }}')"
                            >
                        @endif
                    </div>
                    <select wire:model.live="status"
                            class="w-full bg-slate-800 p-2 rounded-xl text-white">

                        <option value="pending">Pending</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>

                    </select>

                    @if($status === 'verified')

                        <input
                            wire:model="game_username"
                            class="w-full bg-slate-800 p-2 rounded-xl text-white"
                            placeholder="Game Username"
                        >

                        <input
                            wire:model="game_password"
                            class="w-full bg-slate-800 p-2 rounded-xl text-white"
                            placeholder="Game Password"
                        >

                        <input
                            wire:model="game_points_loaded"
                            type="number"
                            step="0.01"
                            class="w-full bg-slate-800 p-2 rounded-xl text-white"
                            placeholder="Game Points Loaded"
                        >

                    @endif

                    <textarea wire:model="admin_notes"
                              class="w-full bg-slate-800 p-2 rounded-xl text-white"
                              placeholder="Notes"></textarea>

                </div>

                <div class="bb-admin-modal-footer p-5 flex justify-end gap-3 border-t border-slate-800">

                    <button type="button" wire:click="closeModal"
                            class="bb-admin-modal-secondary px-4 py-2 bg-gray-700 rounded-xl">
                        Cancel
                    </button>

                    <button type="button" wire:click="processDeposit"
                            wire:loading.attr="disabled"
                            wire:target="processDeposit"
                            class="bb-admin-modal-primary px-4 py-2 bg-green-600 rounded-xl">
                        <span wire:loading.remove wire:target="processDeposit">
    Save
</span>

                        <span wire:loading wire:target="processDeposit">
    Processing...
</span>
                    </button>

                </div>

            </div>

        </div>

    @endif
    @if($this->proofPreview)

        <div class="bb-admin-modal-overlay fixed inset-0 z-[9999] flex items-center justify-center">

            <div class="bb-admin-modal-shell relative max-w-4xl p-4">

                <button
                    wire:click="closeProof"
                    class="bb-admin-modal-close absolute right-2 top-2 z-10"
                    aria-label="Close deposit proof preview"
                >
                    ✕
                </button>

                <img
                    src="{{ asset('storage/'.$this->proofPreview) }}"
                    class="max-h-[calc(100dvh-4rem)] max-w-full rounded-lg object-contain"
                >

            </div>

        </div>

    @endif
</div>
