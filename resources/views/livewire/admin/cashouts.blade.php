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
                <div class="overflow-x-auto scrollbar-purple relative">

                    <table class="min-w-[2200px] text-sm text-left border-collapse">

                    <thead class="text-slate-400 border-b border-slate-800 bg-slate-950">
                    <tr>

                        <th class="
                        px-5
                        py-4
                        whitespace-nowrap
                        sticky
                        left-0
                        z-40
                        bg-slate-950
                        shadow-[4px_0_8px_rgba(0,0,0,0.3)]
                        "
                        >SN</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Ref</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Player</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Username</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Game</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Game Username</th>

                        <th class="px-5 py-4 whitespace-nowrap text-left">Amount</th>

                        <th class="px-5 py-4 whitespace-nowrap text-left">Player Wallet Type</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Player Wallet</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Player QR</th>

                        <th class="px-5 py-4 whitespace-nowrap text-left">Our Wallet Type</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Our Wallet</th>

                        <th class="px-5 py-4 whitespace-nowrap text-left">Status</th>

                        <th class="px-5 py-4 whitespace-nowrap text-left">Handled By</th>
                        <th class="px-5 py-4 whitespace-nowrap text-left">Handled At</th>

                        <th class="px-5 py-4 whitespace-nowrap text-left">Paid At</th>

                        <th class="px-5 py-4 whitespace-nowrap text-left">Payment Proof</th>

                        <th class="px-5 py-4 whitespace-nowrap text-right">Action</th>

                    </tr>
                    </thead>

                    <tbody>

                    @foreach($group as $i => $cashout)

                        @php
                            $isPending = $cashout->status === 'pending';
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
    ">{{ $i + 1 }}</td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $cashout->reference }}</td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $cashout->user?->name }}</td>
                            <td class="text-purple-400">
                                {{ $cashout->user?->username ?? '-' }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $cashout->game?->name }}</td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">
                                {{ $cashout->gameAccount?->game_username }}
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $cashout->amount }}</td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $cashout->wallet_type }}</td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $cashout->wallet_address }}</td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">

                                @if($cashout->qr_image)

                                    <img
                                        src="{{ asset('storage/'.$cashout->qr_image) }}"
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
                                        wire:click="openProof('{{ $cashout->qr_image }}')"
                                    >

                                @else

                                    -

                                @endif

                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">
                                {{ $cashout->wallet?->walletType?->name ?? '-' }}
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">
                                {{ $cashout->wallet?->name ?? '-' }}
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">{{ $cashout->status }}</td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">
                                {{ $cashout->verifier?->name ?? '-' }}
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">
                                {{ $cashout->verified_at ?? '-' }}
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">
                                {{ $cashout->paid_at ?? '-' }}
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-left">

                                @if($cashout->payment_proof)

                                    <img
                                        src="{{ asset('storage/'.$cashout->payment_proof) }}"
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
                                        wire:click="openProof('{{ $cashout->payment_proof }}')"
                                    >

                                @else

                                    -

                                @endif

                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-right">
                                @if($cashout->status === 'paid')

                                    <div class="flex gap-2 justify-end">

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

    <div class="mt-6 custom-page-styles">
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
                    <p class="text-white">
                        Amount:
                       $ {{ $selectedCashout->amount }}
                    </p>


                    <select
                        wire:model.live="status"
                        class="w-full bg-slate-800 rounded-xl p-2 text-white"
                    >
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="rejected">Rejected</option>

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
