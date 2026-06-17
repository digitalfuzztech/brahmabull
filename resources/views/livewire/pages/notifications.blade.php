<div class="max-w-7xl mx-auto px-6 py-6">

    <h1 class="text-2xl font-bold text-white mb-6">
        Notifications
    </h1>

    {{-- SEARCH --}}
    <div class="mb-4 flex gap-3">

        <input
            wire:model.live="search"
            class="bg-slate-800 p-2 text-white rounded-xl w-full"
            placeholder="Search notifications..."
        />

        <select
            wire:model.live="type"
            class="bg-slate-800 p-2 text-white rounded-xl"
        >

            <option value="">
                All Types
            </option>

            <option value="deposit">
                Deposits
            </option>

            <option value="cashout">
                Cashouts
            </option>

            <option value="game">
                Games
            </option>

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

    <div
        class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">

        <table class="w-full text-sm text-left">

            <thead class="text-slate-400 border-b border-slate-800">

            <tr>

                <th class="p-3">
                    Notification
                </th>

                <th>
                    Created
                </th>

                <th>
                    Action
                </th>

            </tr>

            </thead>

            <tbody>

            @foreach($notifications as $notification)

                @php
                    $bgClass = $notification->is_read
                        ? 'opacity-60 bg-transparent'
                        : 'bg-gray-700';
                @endphp

                <tr
                    class="border-b border-slate-800 text-white {{ $bgClass }}"
                >

                    <td class="p-3">

                        <div>

                            <div class="text-indigo-300 font-bold">
                                {{ $notification->title }}
                            </div>

                            <div class="mt-1 whitespace-pre-line">
                                {{ $notification->message }}
                            </div>

                        </div>

                    </td>

                    <td class="text-slate-400">

                        {{ $notification->created_at->diffForHumans() }}

                    </td>

                    <td class="p-3 space-x-2">

                        {{-- PLAY BUTTONS --}}
                        @if($notification->type === 'cashout_paid')

                            <button
                                wire:click="viewProof({{ $notification->id }})"
                                class="px-4 py-2 bg-purple-600 rounded-lg"
                            >
                                View Proof
                            </button>


                        @elseif($notification->type === 'deposit_verified')

                            <a
                                href="{{ $notification->action_url }}" target="_blank"
                                wire:click="markPlayRead({{ $notification->id }})"
                                class="inline-block px-4 py-2 bg-purple-600 rounded-lg"
                            >
                                Play
                            </a>

                            {{-- GOT IT BUTTONS --}}
                        @elseif(
                            in_array(
                                $notification->type,
                                [
                                    'deposit_submitted',
                                    'deposit_rejected',
                                    'cashout_submitted',
                                    'cashout_rejected'
                                ]
                            )
                        )

                            @if($notification->is_read)

                                <button
                                    class="px-3 py-1 bg-green-700 rounded-lg"
                                >
                                    ✓ Got It
                                </button>

                            @else

                                <button
                                    wire:click="markAndRedirect({{ $notification->id }})"
                                    class="px-3 py-1 bg-gray-600 rounded-lg"
                                >
                                    Got It
                                </button>

                            @endif

                            {{-- FALLBACK --}}
                        @else

                            @if($notification->is_read)

                                <button
                                    wire:click="toggleRead({{ $notification->id }})"
                                    class="px-3 py-1 bg-yellow-600 rounded-lg"
                                >
                                    Mark Unread
                                </button>

                            @else

                                <button
                                    wire:click="toggleRead({{ $notification->id }})"
                                    class="px-3 py-1 bg-red-600 rounded-lg"
                                >
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

    <div class="mt-4 custom-page-styles">

        {{ $notifications->links() }}

    </div>
    @if(!empty($previewImage))
        <div class="fixed inset-0 bg-black/80 flex items-center justify-center z-[9999]">
            <div class="relative bg-slate-900 p-4 rounded-xl">

                <button
                    wire:click="closePreview"
                    class="absolute top-2 right-2 bg-red-600 px-2 rounded"
                >
                    ✕
                </button>

                <img
                    src="{{ $previewImage }}"
                    class="max-h-[600px] rounded-lg"
                >

            </div>
        </div>
    @endif

</div>
