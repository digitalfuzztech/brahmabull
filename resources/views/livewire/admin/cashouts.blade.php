<div>

    <h1 class="text-2xl font-bold text-white mb-6">
        Cashouts
    </h1>

    @if(session()->has('success'))
        <div
            x-data="{show:true}"
            x-init="setTimeout(() => show = false, 5000)"
            x-show="show"
            class="mb-4 p-3 bg-green-600 text-white rounded-xl"
        >
            {{ session('success') }}
        </div>
    @endif
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">

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
            <option value="">
                All Games
            </option>

            @foreach($this->games as $game)
                <option value="{{ $game->id }}">
                    {{ $game->name }}
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

            <option value="rejected">
                Rejected
            </option>

            <option value="paid">
                Paid
            </option>

        </select>

    </div>
    @foreach($this->cashouts as $date => $group)
        <div class="grid grid-cols-1 mb-4">


            <h2 class="text-lg font-bold text-white mb-3">
                {{ $date }}
            </h2>

            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-auto">
                <div class="overflow-x-auto scrollbar-purple">
                    <table class="min-w-[1700px] text-sm text-left">

                    <thead class="border-b border-slate-800 text-slate-400">
                    <tr>

                        <th class="p-3">SN</th>
                        <th>Ref</th>
                        <th>Player</th>
                        <th>Game</th>
                        <th>Username</th>

                        <th>Amount</th>

                        <th>Player Wallet Type</th>
                        <th>Player Wallet</th>
                        <th>Player QR</th>

                        <th>Our Wallet Type</th>
                        <th>Our Wallet</th>

                        <th>Status</th>

                        <th>Handled By</th>
                        <th>Handled At</th>

                        <th>Paid At</th>

                        <th>Payment Proof</th>

                        <th>Action</th>

                    </tr>
                    </thead>

                    <tbody>

                    @foreach($group as $i => $cashout)

                        @php
                            $isPending = $cashout->status === 'pending';
                        @endphp

                        <tr class="
    border-b border-slate-800 text-white
    {{ $isPending ? 'bg-gray-700/60' : 'opacity-80' }}
">
                            <td class="p-3">{{ $i + 1 }}</td>

                            <td>{{ $cashout->reference }}</td>

                            <td>{{ $cashout->user?->name }}</td>

                            <td>{{ $cashout->game?->name }}</td>

                            <td>
                                {{ $cashout->gameAccount?->game_username }}
                            </td>

                            <td>{{ $cashout->amount }}</td>

                            <td>{{ $cashout->wallet_type }}</td>

                            <td>{{ $cashout->wallet_address }}</td>

                            <td>

                                @if($cashout->qr_image)

                                    <img
                                        src="{{ asset('storage/'.$cashout->qr_image) }}"
                                        class="h-10 w-10 rounded-lg object-cover cursor-pointer"
                                        wire:click="openProof('{{ $cashout->qr_image }}')"
                                    >

                                @else

                                    -

                                @endif

                            </td>

                            <td>
                                {{ $cashout->wallet?->walletType?->name ?? '-' }}
                            </td>

                            <td>
                                {{ $cashout->wallet?->name ?? '-' }}
                            </td>

                            <td>{{ $cashout->status }}</td>

                            <td>
                                {{ $cashout->verifier?->name ?? '-' }}
                            </td>

                            <td>
                                {{ $cashout->verified_at ?? '-' }}
                            </td>

                            <td>
                                {{ $cashout->paid_at ?? '-' }}
                            </td>

                            <td>

                                @if($cashout->payment_proof)

                                    <img
                                        src="{{ asset('storage/'.$cashout->payment_proof) }}"
                                        class="h-10 w-10 rounded-lg object-cover cursor-pointer"
                                        wire:click="openProof('{{ $cashout->payment_proof }}')"
                                    >

                                @else

                                    -

                                @endif

                            </td>

                            <td>
                                @if($cashout->status === 'paid')

                                    <div class="flex gap-2">

        <span class="px-3 py-1 bg-green-700 rounded-lg">
            Paid
        </span>

                                        @role('admin')
                                        <button
                                            wire:click="openModal({{ $cashout->id }})"
                                            class="px-3 py-1 bg-yellow-600 rounded-lg"
                                        >
                                            Edit
                                        </button>
                                        @endrole

                                    </div>

                                @else

                                    <button
                                        wire:click="openModal({{ $cashout->id }})"
                                        class="px-3 py-1 bg-purple-600 rounded-lg"
                                    >
                                        Process
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

    <div class="mt-6">
        {{ $this->cashouts->links() }}
    </div>


    @if($selectedCashout)

        <div class="fixed inset-0 bg-black/70 flex items-center justify-center z-[999]">

            <div class="w-full max-w-xl h-[70vh] flex flex-col m-auto rounded-2xl bg-slate-900 border border-slate-700 overflow-hidden">

                <div class="p-5 border-b border-slate-800 flex justify-between items-center">

                    <h2 class="text-white font-bold">
                        {{ $selectedCashout->reference }} Processing
                    </h2>
                    <button wire:click="closeModal">
                        ✕
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-4 min-h-0 custom-scrollbar">

                    <p class="text-white">
                        Player:
                        {{ $selectedCashout->user?->name }}
                    </p>

                    <p class="text-white">
                        Game:
                        {{ $selectedCashout->game?->name }}
                    </p>

                    <select
                        wire:model.live="status"
                        class="w-full bg-slate-800 rounded-xl p-2 text-white"
                    >
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="paid">Paid</option>
                    </select>

                    @if($status === 'paid')

                        <select
                            wire:model="wallet_id"
                            class="w-full bg-slate-800 rounded-xl p-2 text-white"
                        >
                            <option value="">
                                Select Wallet Used For Payment
                            </option>

                            @foreach($this->wallets as $wallet)

                                <option value="{{ $wallet->id }}">
                                    {{ $wallet->walletType?->name }}
                                    -
                                    {{ $wallet->name }}
                                </option>

                            @endforeach

                        </select>

                    @endif

                    <textarea
                        wire:model="remarks"
                        class="w-full bg-slate-800 rounded-xl p-2 text-white"
                        placeholder="Remarks"
                    ></textarea>

                    @if($status === 'paid')

                        <div>

                            <label class="block text-sm font-medium text-slate-300 mb-2">
                                Upload Payment Proof
                            </label>

                            <label
                                for="payment_proof"
                                class="group flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-600 bg-slate-800/40 p-6 transition hover:border-indigo-500"
                            >

                                @if($payment_proof)

                                    <img
                                        src="{{ $payment_proof->temporaryUrl() }}"
                                        class="max-h-52 rounded-xl border border-indigo-500"
                                    >

                                    <span class="mt-3 text-indigo-300">
                    Change Payment Proof
                </span>

                                @elseif($selectedCashout?->payment_proof)

                                    <img
                                        src="{{ asset('storage/'.$selectedCashout->payment_proof) }}"
                                        class="max-h-52 rounded-xl border border-indigo-500"
                                    >

                                    <span class="mt-3 text-indigo-300">
                    Change Payment Proof
                </span>

                                @else

                                    <span class="text-slate-300">
                    Upload Payment Screenshot
                </span>

                                @endif

                                <input
                                    id="payment_proof"
                                    type="file"
                                    wire:model="payment_proof"
                                    class="hidden"
                                    accept="image/*"
                                >

                            </label>

                            @error('payment_proof')
                            <p class="mt-2 text-sm text-red-400">
                                {{ $message }}
                            </p>
                            @enderror

                            <div
                                wire:loading
                                wire:target="payment_proof"
                                class="mt-2 text-sm text-indigo-400"
                            >
                                Uploading...
                            </div>

                        </div>

                    @endif

                </div>

                <div class="p-5 border-t border-slate-800 flex justify-end gap-3">

                    <button
                        wire:click="closeModal"
                        class="px-4 py-2 bg-gray-700 rounded-xl"
                    >
                        Cancel
                    </button>

                    <button
                        wire:click="processCashout"
                        wire:loading.attr="disabled"
                        class="px-4 py-2 bg-green-600 rounded-xl"
                    >

                        <span wire:loading.remove>
                            Save
                        </span>

                        <span wire:loading>
                            Processing...
                        </span>

                    </button>

                </div>

            </div>

        </div>

    @endif


    @if($proofPreview)

        <div class="fixed inset-0 bg-black/80 flex items-center justify-center z-[9999]">

            <div class="relative bg-slate-900 p-4 rounded-xl">

                <button
                    wire:click="closeProof"
                    class="absolute top-2 right-2 bg-red-600 text-white px-2 rounded"
                >
                    ✕
                </button>

                <img
                    src="{{ asset('storage/'.$proofPreview) }}"
                    class="max-h-[600px] rounded-lg"
                >

            </div>

        </div>

    @endif

</div>
