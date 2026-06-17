<div>

    <h1 class="text-2xl font-bold text-white mb-6">
        Notifications
    </h1>

    {{-- SEARCH FILTER --}}
    <div class="mb-4 flex gap-3">

        <input
            wire:model.live="search"
            class="bg-slate-800 p-2 text-white rounded-xl w-full"
            placeholder="Search by player name..."
        />

        <select wire:model.live="type"
                class="bg-slate-800 p-2 text-white rounded-xl">

            <option value="">All Types</option>
            <option value="deposit_created">Deposits</option>
            <option value="cashout_created">Cashouts</option>
            <option value="wallet">Wallet</option>
            <option value="game">Games</option>

        </select>
        <select
            wire:model.live="readStatus"
            class="bg-slate-800 p-2 text-white rounded-xl"
        >
            <option value="">
                All Status
            </option>

            <option value="0">
                Unread
            </option>

            <option value="1">
                Read
            </option>
        </select>
    </div>

    {{-- TABLE --}}
    <div class="grid grid-cols-1 mb-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto scrollbar-purple">
        <table class="w-full text-sm text-left">

            <thead class="text-slate-400 border-b border-slate-800">
            <tr>
                <th class="p-3">Notification</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
            </thead>

            <tbody>

            @foreach($this->notifications as $notification)

                @php
                    $bgClass = $notification->is_read
                        ? 'opacity-60 bg-transparent'
                        : 'bg-gray-700';
                @endphp

                <tr class="border-b border-slate-800 text-white {{ $bgClass }}">

                    <td class="p-3">

                        <div class="{{ $notification->is_read ? 'opacity-60' : 'font-bold' }}">


                                <div class="text-sm text-indigo-300 font-bold">
                                    {{ $notification->title }}
                                </div>

                                <div class="text-sm text-white mt-1">
                                    {{ $notification->message }}
                                </div>

                        </div>

                    </td>

                    <td class="text-slate-400">
                        {{ $notification->created_at->diffForHumans() }}
                    </td>

                    <td class="p-3 space-x-2">

                        {{-- DEPOSIT --}}
                        @if($notification->type === 'deposit_created')
                            <button
                                wire:click="markAndRedirect({{ $notification->id }})"
                                class="px-3 py-1 bg-purple-600 rounded-lg"
                            >
                                View Deposits
                            </button>
                        @endif

                        {{-- CASHOUT --}}
                        @if($notification->type === 'cashout_created')
                            <button
                                wire:click="markAndRedirect({{ $notification->id }})"
                                class="px-3 py-1 bg-green-600 rounded-lg"
                            >
                                View Cashouts
                            </button>
                        @endif
                        @if($notification->type === 'cashout_admin')
                            <button
                                wire:click="markAndRedirect({{ $notification->id }})"
                                class="px-3 py-1 bg-green-600 rounded-lg"
                            >
                                View Cashouts
                            </button>
                        @endif
                        {{-- WALLET --}}
                        @if($notification->type === 'wallet')
                            <button
                                wire:click="markAndRedirect({{ $notification->id }})"
                                class="px-3 py-1 bg-blue-600 rounded-lg"
                            >
                               Go To Wallets
                            </button>
                        @endif

                        {{-- GAME --}}
                        @if($notification->type === 'game')

                            @if($notification->is_read)

                                <button class="px-3 py-1 bg-green-700 rounded-lg">
            ✓ Got It
        </button>

                            @else

                                <button
                                    wire:click="markAndRedirect({{ $notification->id }})"
                                    class="px-3 py-1 bg-gray-600 rounded-lg"
                                >
                                    Got It!
                                </button>

                            @endif

                        @endif

                        {{-- 🔥 FALLBACK (IMPORTANT FIX) --}}
                        @if(!in_array($notification->type, ['deposit_created','cashout_created','wallet','game','cashout_admin']))

                            @if($notification->is_read)

                                <button
                                    wire:click="toggleRead({{ $notification->id }})"
                                    class="px-3 py-1 bg-yellow-600 rounded-lg">
                                    Mark Unread
                                </button>

                            @else

                                <button
                                    wire:click="toggleRead({{ $notification->id }})"
                                    class="px-3 py-1 bg-red-600 rounded-lg">
                                    Mark Read
                                </button>

                            @endif

                        @endif

                    </td>

                </tr>

            @endforeach

            </tbody>

        </table>
        </div>
    </div>
    </div>
    <div class="mt-4 custom-page-styles">
        {{ $notifications->links() }}
    </div>

</div>
