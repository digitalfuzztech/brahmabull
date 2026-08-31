<div>
    <h1 class="mb-6 text-2xl font-bold text-white">Brahma Plays</h1>

    <div class="mb-6 grid grid-cols-1 gap-3 md:grid-cols-4">
        <input type="date" wire:model.live="searchDate" class="rounded-xl bg-slate-800 p-2">
        <input wire:model.live="search" placeholder="Ref / Player / Username" class="rounded-xl bg-slate-800 p-2">

        <select wire:model.live="gameFilter" class="rounded-xl bg-slate-800 p-2">
            <option value="">All Games</option>
            @foreach($this->games as $game)
                <option value="{{ $game->id }}">{{ $game->name }}</option>
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

    @foreach($this->plays as $date => $group)
        <div class="mb-4 grid grid-cols-1">
            <h2 class="mb-3 text-lg font-bold text-white">{{ $date }}</h2>

            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
                <div class="scrollbar-purple relative overflow-x-auto">
                    <table class="min-w-[1800px] border-collapse text-left text-sm">
                        <thead class="border-b border-slate-800 bg-slate-950 text-slate-400">
                        <tr>
                            <th class="sticky left-0 z-40 bg-slate-950 px-5 py-4 shadow-[4px_0_8px_rgba(0,0,0,0.3)]">S.N.</th>
                            <th class="whitespace-nowrap px-5 py-4">Date/Time</th>
                            <th class="whitespace-nowrap px-5 py-4">Reference</th>
                            <th class="whitespace-nowrap px-5 py-4">Player</th>
                            <th class="whitespace-nowrap px-5 py-4">Username</th>
                            <th class="whitespace-nowrap px-5 py-4">Player ID</th>
                            <th class="whitespace-nowrap px-5 py-4">Game</th>
                            <th class="whitespace-nowrap px-5 py-4">Balance at Submission</th>
                            <th class="whitespace-nowrap px-5 py-4">Points</th>
                            <th class="whitespace-nowrap px-5 py-4">Game Username</th>
                            <th class="whitespace-nowrap px-5 py-4">Status</th>
                            <th class="whitespace-nowrap px-5 py-4">Processed By</th>
                            <th class="whitespace-nowrap px-5 py-4">Processed At</th>
                            <th class="whitespace-nowrap px-5 py-4 text-right">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($group as $i => $play)
                            @php($isPending = $play->status === 'pending')
                            <tr class="border-b border-slate-800 text-white transition hover:bg-gray-400/40 {{ $isPending ? 'bg-gray-700/60' : 'bg-transparent opacity-80' }}">
                                <td class="sticky left-0 z-30 bg-slate-900 px-5 py-4 shadow-[4px_0_8px_rgba(0,0,0,0.3)]">{{ $i + 1 }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $play->created_at->format('Y-m-d H:i:s') }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $play->reference }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $play->user?->name }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-purple-400">{{ $play->user?->username ?? '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $play->user?->playerProfile?->player_id ?? '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $play->game?->name }}</td>
                                <td class="whitespace-nowrap px-5 py-4">${{ number_format((float) $play->balance_at_submission, 2) }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ number_format((float) $play->points_to_load, 2) }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $play->game_username ?? '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="{{ $play->status === 'verified' ? 'text-green-400' : ($play->status === 'rejected' ? 'text-red-400' : 'text-yellow-400') }}">{{ $play->status }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $play->processor?->name ?? $play->verifier?->name ?? '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4">{{ $play->processed_at ? $play->processed_at->format('Y-m-d H:i:s') : '-' }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    @if($play->debited_at)
                                        <span class="rounded-lg bg-green-700 px-3 py-1">Verified</span>
                                    @elseif($play->status === 'rejected')
                                        <span class="rounded-lg bg-red-700 px-3 py-1">Rejected</span>
                                    @else
                                        <button wire:click="openModal({{ $play->id }})" class="rounded-lg bg-purple-600 px-3 py-1">Process Request</button>
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
        {{ $this->plays->links() }}
    </div>

    @if($selectedPlay)
        <div class="fixed inset-0 z-[999] flex items-center justify-center bg-black/70 p-4">
            <div class="flex max-h-[80vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-slate-700 bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-800 p-5">
                    <h2 class="font-bold text-white">{{ $selectedPlay->reference }} Processing</h2>
                    <button wire:click="closeModal" class="text-white">x</button>
                </div>

                <div class="custom-scrollbar min-h-0 flex-1 space-y-3 overflow-y-auto p-5 text-white">
                    <p>Player: {{ $selectedPlay->user?->name }}</p>
                    <p>Username: {{ $selectedPlay->user?->username ?? '-' }}</p>
                    <p>Player ID: {{ $selectedPlay->user?->playerProfile?->player_id ?? '-' }}</p>
                    <p>Reference: {{ $selectedPlay->reference }}</p>
                    <p>Date: {{ $selectedPlay->created_at->format('Y-m-d H:i:s') }}</p>
                    <p>Available Balance at Submission: ${{ number_format((float) $selectedPlay->balance_at_submission, 2) }}</p>
                    <p>Current Brahma Balance: ${{ number_format((float) $selectedPlay->user?->fresh()?->brahma_balance, 2) }}</p>
                    <p>Game: {{ $selectedPlay->game?->name }}</p>
                    <p>Points to Load: {{ number_format((float) $selectedPlay->points_to_load, 2) }}</p>

                    <select wire:model.live="status" class="w-full rounded-xl bg-slate-800 p-2 text-white">
                        <option value="pending">Pending</option>
                        <option value="verified">Verified</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    @error('status') <p class="text-sm text-red-400">{{ $message }}</p> @enderror

                    @if($status === 'verified')
                        <div>
                            <label class="text-sm text-slate-400">Game Username</label>
                            <input wire:model="game_username" class="mt-1 w-full rounded-xl bg-slate-800 p-2 text-white" placeholder="Game Username">
                            @error('game_username') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="text-sm text-slate-400">Game Password</label>
                            <input wire:model="game_password" class="mt-1 w-full rounded-xl bg-slate-800 p-2 text-white" placeholder="Game Password">
                            @error('game_password') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    @if($status === 'rejected')
                        <div>
                            <label class="text-sm text-slate-400">Rejection Note</label>
                            <textarea wire:model="rejection_note" class="mt-1 w-full rounded-xl bg-slate-800 p-2 text-white" placeholder="Reason"></textarea>
                            @error('rejection_note') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-800 p-5">
                    <button wire:click="closeModal" class="rounded-xl bg-gray-700 px-4 py-2">Cancel</button>
                    <button wire:click="processPlay" wire:loading.attr="disabled" wire:target="processPlay" class="rounded-xl bg-green-600 px-4 py-2 disabled:opacity-70">
                        <span wire:loading.remove wire:target="processPlay">Save</span>
                        <span wire:loading wire:target="processPlay">Processing...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
