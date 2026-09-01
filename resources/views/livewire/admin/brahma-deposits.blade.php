<div>
    <h1 class="mb-6 text-2xl font-bold text-white">Brahma Deposits</h1>

    <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-5">
        <input type="date" wire:model.live="searchDate" class="rounded-xl bg-slate-800 p-2">
        <input wire:model.live="search" placeholder="Ref / Player / Username" class="rounded-xl bg-slate-800 p-2">

        <select wire:model.live="walletTypeFilter" class="rounded-xl bg-slate-800 p-2">
            <option value="">All Wallet Types</option>
            @foreach($this->walletTypes as $type)
                <option value="{{ $type->id }}">{{ $type->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="walletFilter" class="rounded-xl bg-slate-800 p-2">
            <option value="">All Wallets</option>
            @foreach($this->wallets as $wallet)
                <option value="{{ $wallet->id }}">{{ $wallet->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="statusFilter" class="rounded-xl bg-slate-800 p-2">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="verified">Verified</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>

    @if(session()->has('success'))
        <div x-data="{show:true}" x-init="setTimeout(() => show = false, 5000)" x-show="show" class="mb-4 rounded-xl bg-green-600 p-3 text-white">
            {{ session('success') }}
        </div>
    @endif

    @foreach($this->deposits as $date => $group)
        <div class="mb-4 grid grid-cols-1">
            <h2 class="mb-3 text-lg font-bold text-white">{{ $date }}</h2>

            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
                <div class="scrollbar-purple relative overflow-x-auto">
                    <table class="min-w-[1900px] border-collapse text-left text-sm">
                        <thead class="border-b border-slate-800 bg-slate-950 text-slate-400">
                        <tr>
                            <th class="sticky left-0 z-40 bg-slate-950 px-5 py-4 shadow-[4px_0_8px_rgba(0,0,0,0.3)]">S.N.</th>
                            <th class="whitespace-nowrap px-5 py-4">Date/Time</th>
                            <th class="whitespace-nowrap px-5 py-4">Reference</th>
                            <th class="whitespace-nowrap px-5 py-4">Player</th>
                            <th class="whitespace-nowrap px-5 py-4">Username</th>
                            <th class="whitespace-nowrap px-5 py-4">Player ID</th>
                            <th class="whitespace-nowrap px-5 py-4">Deposit Amount</th>
                            <th class="whitespace-nowrap px-5 py-4">Load Balance</th>
                            <th class="whitespace-nowrap px-5 py-4">Wallet Type</th>
                            <th class="whitespace-nowrap px-5 py-4">Wallet</th>
                            <th class="whitespace-nowrap px-5 py-4">Account</th>
                            <th class="whitespace-nowrap px-5 py-4">Screenshot</th>
                            <th class="whitespace-nowrap px-5 py-4">Status</th>
                            <th class="whitespace-nowrap px-5 py-4">Processed By</th>
                            <th class="whitespace-nowrap px-5 py-4">Processed At</th>
                            <th class="whitespace-nowrap px-5 py-4 text-right">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($group as $i => $deposit)
                            @php($isPending = $deposit->status === 'pending')
                            <tr class="border-b border-slate-800 text-white transition hover:bg-gray-400/40 {{ $isPending ? 'bg-gray-700/60' : 'bg-transparent opacity-80' }}">
                                <td class="sticky left-0 z-30 bg-slate-900 px-5 py-4 shadow-[4px_0_8px_rgba(0,0,0,0.3)]">{{ $i + 1 }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $deposit->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $deposit->reference }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $deposit->user?->name }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-purple-400">{{ $deposit->user?->username ?? '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $deposit->user?->playerProfile?->player_id ?? '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">${{ number_format((float) $deposit->amount, 2) }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $deposit->load_balance ? '$' . number_format((float) $deposit->load_balance, 2) : '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $deposit->wallet_type ?? $deposit->wallet?->walletType?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $deposit->wallet_name ?? $deposit->wallet?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $deposit->wallet_account_identifier ?? $deposit->wallet?->account_identifier ?? '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    @if($deposit->proof_image)
                                        <img src="{{ asset('storage/'.$deposit->proof_image) }}" class="h-14 w-14 cursor-pointer rounded-xl border border-slate-700 object-cover transition hover:scale-105" wire:click="openProof('{{ $deposit->proof_image }}')">
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="{{ $deposit->status === 'verified' ? 'text-green-400' : ($deposit->status === 'rejected' ? 'text-red-400' : 'text-yellow-400') }}">
                                        {{ $deposit->status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $deposit->processor?->name ?? $deposit->verifier?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $deposit->processed_at ? $deposit->processed_at->format('Y-m-d H:i:s') : '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @if($deposit->credited_at)
                                        @if(auth()->user()?->hasRole('admin'))
                                            <button wire:click="openModal({{ $deposit->id }})" class="rounded-lg bg-purple-600 px-3 py-1">Edit</button>
                                        @else
                                            <span class="rounded-lg bg-green-700 px-3 py-1">Verified</span>
                                        @endif
                                    @elseif($deposit->status === 'rejected')
                                        @if(auth()->user()?->hasRole('admin'))
                                            <button wire:click="openModal({{ $deposit->id }})" class="rounded-lg bg-purple-600 px-3 py-1">Edit</button>
                                        @else
                                            <span class="rounded-lg bg-red-700 px-3 py-1">Rejected</span>
                                        @endif
                                    @else
                                        <button wire:click="openModal({{ $deposit->id }})" class="rounded-lg bg-purple-600 px-3 py-1">Process Deposit</button>
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

    @if($selectedDeposit)
        <div class="fixed inset-0 z-[999] flex items-center justify-center bg-black/70 p-4">
            <div class="flex max-h-[80vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-800 p-5">
                    <h2 class="font-bold text-white">{{ $selectedDeposit->reference }} Processing</h2>
                    <button wire:click="closeModal" class="text-white">x</button>
                </div>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-5 text-white custom-scrollbar">
                    <p>Player: {{ $selectedDeposit->user?->name }}</p>
                    <p>Username: {{ $selectedDeposit->user?->username ?? '-' }}</p>
                    <p>Player ID: {{ $selectedDeposit->user?->playerProfile?->player_id ?? '-' }}</p>
                    <p>Reference: {{ $selectedDeposit->reference }}</p>
                    <p>Date: {{ $selectedDeposit->created_at->format('Y-m-d H:i:s') }}</p>
                    <p>Deposit Amount: ${{ number_format((float) $selectedDeposit->amount, 2) }} <span class="text-sm text-slate-400">(player submitted)</span></p>
                    <p>Deposited To: {{ $selectedDeposit->wallet_type ?? '-' }}</p>
                    <p>Wallet: {{ $selectedDeposit->wallet_name ?? '-' }}</p>
                    <p>Account: {{ $selectedDeposit->wallet_account_identifier ?? '-' }}</p>

                    @if($selectedDeposit->proof_image)
                        <img src="{{ asset('storage/'.$selectedDeposit->proof_image) }}" class="h-20 w-20 cursor-pointer rounded-xl object-cover" wire:click="openProof('{{ $selectedDeposit->proof_image }}')">
                    @endif

                    @if($selectedDeposit->credited_at)
                        <p>Status: <span class="font-semibold text-green-400">Verified (financially applied)</span></p>
                    @else
                        <select wire:model.live="status" class="w-full rounded-xl bg-slate-800 p-2 text-white">
                            <option value="pending">Pending</option>
                            <option value="verified">Verified</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    @endif
                    @error('status') <p class="text-sm text-red-400">{{ $message }}</p> @enderror

                    @if($status === 'verified')
                        <div>
                            <label class="text-sm text-slate-400">Load Balance</label>
                            @if($selectedDeposit->credited_at && !auth()->user()?->hasRole('admin'))
                                <p class="mt-1 rounded-xl bg-slate-800 p-2 text-white">${{ number_format((float) $selectedDeposit->load_balance, 2) }} (applied)</p>
                            @else
                                <input wire:model="load_balance" type="number" step="0.01" class="mt-1 w-full rounded-xl bg-slate-800 p-2 text-white" placeholder="Amount to add">
                            @endif
                            @error('load_balance') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <textarea wire:model="admin_notes" class="w-full rounded-xl bg-slate-800 p-2 text-white" placeholder="Notes"></textarea>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-800 p-5">
                    @php($depositSaveMethod = auth()->user()?->hasRole('admin') ? 'adminProcessDeposit' : 'processDeposit')
                    <button wire:click="closeModal" class="rounded-xl bg-gray-700 px-4 py-2">Cancel</button>
                    <button wire:click="{{ $depositSaveMethod }}" wire:loading.attr="disabled" wire:target="{{ $depositSaveMethod }}" class="rounded-xl bg-green-600 px-4 py-2 disabled:opacity-70">
                        <span wire:loading.remove wire:target="{{ $depositSaveMethod }}">Save</span>
                        <span wire:loading wire:target="{{ $depositSaveMethod }}">Processing...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if($proofPreview)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 p-4">
            <div class="relative rounded-xl bg-slate-900 p-4">
                <button wire:click="closeProof" class="absolute right-2 top-2 rounded bg-red-600 px-2 text-white">x</button>
                <img src="{{ asset('storage/'.$proofPreview) }}" class="max-h-[600px] rounded-lg">
            </div>
        </div>
    @endif
</div>
