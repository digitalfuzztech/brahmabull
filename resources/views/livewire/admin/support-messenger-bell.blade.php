<div
    class="relative"
    x-data="{ dropdownOpen: false }"
    x-on:brahma-header-dropdown-open.window="if ($event.detail.source !== 'support') { dropdownOpen = false }"
    x-on:keydown.escape.window="dropdownOpen = false"
    wire:poll.2s="refreshSupportMessenger"
>
    <button type="button" x-on:click="dropdownOpen = !dropdownOpen; if (dropdownOpen) { $dispatch('brahma-header-dropdown-open', { source: 'support' }) }" x-bind:aria-expanded="dropdownOpen" class="relative rounded-lg p-2 text-white hover:bg-slate-800" aria-label="Open Support Messages">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 13a8 8 0 0 1 16 0"/><path stroke-linecap="round" stroke-linejoin="round" d="M18 19c0 1.1-.9 2-2 2h-3"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 13v4a2 2 0 0 0 2 2h1v-8H6a2 2 0 0 0-2 2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M20 13v4a2 2 0 0 1-2 2h-1v-8h1a2 2 0 0 1 2 2Z"/></svg>
        <span data-support-unread-badge data-unread-count="{{ $unreadCount }}" data-unread="{{ $unreadCount > 0 ? 'true' : 'false' }}" class="absolute -right-1 -top-1 min-w-5 rounded-full bg-cyan-600 px-1.5 text-center text-[10px] font-black leading-5 text-white {{ $unreadCount > 0 ? 'inline-flex items-center justify-center' : 'hidden' }}">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
    </button>

        <div data-support-dropdown x-cloak x-show="dropdownOpen" x-on:click.outside="dropdownOpen = false" class="absolute right-0 top-11 z-[9999] w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl">
            <div class="border-b border-slate-700 px-4 py-3"><h3 class="font-bold text-white">Support Messages</h3><p class="text-xs text-slate-400">Recent authorized player conversations</p></div>
            <div class="max-h-80 overflow-y-auto">
                @forelse($recent as $conversation)
                    <button type="button" wire:key="support-bell-conversation-{{ $conversation['id'] }}" data-support-dropdown-conversation-id="{{ $conversation['id'] }}" wire:click="openConversation({{ $conversation['id'] }})" class="block w-full border-b border-slate-800 px-4 py-3 text-left hover:bg-slate-800 {{ $conversation['unread_count'] ? 'bg-cyan-500/10' : '' }}">
                        <div class="flex justify-between gap-3"><p class="truncate text-sm {{ $conversation['unread_count'] ? 'font-black text-white' : 'font-bold text-slate-200' }}">{{ $conversation['name'] }}</p><span class="shrink-0 text-[10px] text-slate-500">{{ $conversation['relative_time'] }}</span></div>
                        <div class="mt-1 flex items-center justify-between gap-3"><p class="truncate text-xs text-slate-400">{{ $conversation['preview'] }}</p>@if($conversation['unread_count'])<span class="rounded-full bg-cyan-600 px-2 py-0.5 text-[10px] font-bold">{{ $conversation['unread_count'] }}</span>@endif</div>
                    </button>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-slate-400">No recent Support conversations.</p>
                @endforelse
            </div>
            <button type="button" wire:click="goToSupportInbox" class="block w-full border-t border-slate-700 bg-slate-950 px-4 py-3 text-center text-sm font-bold text-cyan-300 hover:bg-slate-800">Go To Support Inbox</button>
        </div>
</div>
