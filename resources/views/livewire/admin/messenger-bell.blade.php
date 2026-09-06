<div
    class="relative"
    wire:poll.2s="pollUnread"
    x-data="{ dropdownOpen: false }"
    x-on:brahma-header-dropdown-open.window="if ($event.detail.source !== 'team') { dropdownOpen = false }"
    x-on:keydown.escape.window="dropdownOpen = false"
>
    <button type="button" x-on:click="dropdownOpen = !dropdownOpen; if (dropdownOpen) { $dispatch('brahma-header-dropdown-open', { source: 'team' }) }" x-bind:aria-expanded="dropdownOpen" class="relative rounded-lg p-2 text-white hover:bg-slate-800" aria-label="Open Messenger">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
        <span
            data-team-unread-badge
            data-unread-count="{{ $unreadCount }}"
            data-unread="{{ $unreadCount > 0 ? 'true' : 'false' }}"
            class="absolute -right-1 -top-1 z-10 min-w-5 rounded-full bg-purple-600 px-1.5 text-center text-[10px] font-black leading-5 text-white ring-2 ring-slate-950 {{ $unreadCount > 0 ? 'inline-flex items-center justify-center' : 'hidden' }}"
        >{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
    </button>

        <div data-team-dropdown x-cloak x-show="dropdownOpen" x-on:click.outside="dropdownOpen = false" class="absolute right-0 top-11 z-[9999] w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="border-b border-slate-700 px-4 py-3"><h3 class="font-bold text-white">Messenger</h3><p class="text-xs text-slate-400">Recent authorized conversations</p></div>
            <div class="max-h-80 overflow-y-auto">
                @forelse($recent as $conversation)
                    <button type="button" wire:key="team-bell-conversation-{{ $conversation['id'] }}" data-team-dropdown-conversation-id="{{ $conversation['id'] }}" wire:click="openConversation({{ $conversation['id'] }})" class="block w-full border-b border-slate-800 px-4 py-3 text-left hover:bg-slate-800 {{ $conversation['unread_count'] ? 'bg-purple-500/10' : '' }}">
                        <div class="flex justify-between gap-3"><p class="truncate text-sm {{ $conversation['unread_count'] ? 'font-black text-white' : 'font-bold text-slate-200' }}">{{ $conversation['name'] }}</p><span class="shrink-0 text-[10px] text-slate-500">{{ $conversation['relative_time'] }}</span></div>
                        <div class="mt-1 flex items-center justify-between gap-3">

                            <p
                                class="
            min-w-0 flex-1 truncate text-xs

            {{ $conversation['unread_count'] > 0
                ? 'font-bold text-white'
                : 'font-normal text-slate-400' }}
        "
                            >
                                {{ ucfirst($conversation['domain']) }}
                                ·
                                {{ $conversation['preview'] }}
                            </p>

                            <span
                                data-team-dropdown-unread
                                data-unread-count="{{ $conversation['unread_count'] }}"

                                class="
            shrink-0
            rounded-full
            bg-purple-600
            px-2 py-0.5
            text-[10px]
            font-bold
            text-white

            {{ $conversation['unread_count'] > 0
                ? 'inline-flex items-center justify-center'
                : 'hidden' }}
        "
                            >
        {{ $conversation['unread_count'] > 99
            ? '99+'
            : $conversation['unread_count'] }}
    </span>

                        </div>
                    </button>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-slate-400">No recent conversations.</p>
                @endforelse
            </div>
            <button type="button" wire:click="goToInbox" class="block w-full border-t border-slate-700 bg-slate-950 px-4 py-3 text-center text-sm font-bold text-purple-300 hover:bg-slate-800 hover:text-purple-200">
                Go To Inbox
            </button>
        </div>
</div>
