<div class="flex h-[calc(100dvh-9rem)] min-h-0 flex-col overflow-hidden">
    <div class="mb-4 flex shrink-0 gap-2 rounded-xl border border-slate-800 bg-slate-900 p-1">
        <button type="button" wire:click="selectDomain('support')" class="flex-1 rounded-lg px-4 py-2 text-sm font-bold {{ $domain === 'support' ? 'bg-purple-600 text-white' : 'text-slate-400 hover:bg-slate-800' }}">Support</button>
        <button type="button" wire:click="selectDomain('team')" class="flex-1 rounded-lg px-4 py-2 text-sm font-bold {{ $domain === 'team' ? 'bg-purple-600 text-white' : 'text-slate-400 hover:bg-slate-800' }}">Team</button>
    </div>

    @if($domain === 'team')
        <div class="min-h-0 flex-1"><livewire:admin.team-messenger :initial-conversation-id="$deepLinkedConversationId" /></div>
    @else
<div
    @if($selectedConversationId)
        wire:poll.3s.visible="pollInbox"
    @else
        wire:poll.5s.visible="pollInbox"
    @endif
    class="flex min-h-0 flex-1 flex-col overflow-hidden"
>
    <div class="mb-4 flex shrink-0 items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-white">Support Inbox</h1>
            <p class="mt-1 text-sm text-slate-400">Player conversations with the Brahmabull Support Team</p>
        </div>
        <span class="rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-xs text-slate-300">
            {{ count($conversations) }} conversations
        </span>
    </div>

    @error('conversation')
        <div class="mb-4 rounded-xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">{{ $message }}</div>
    @enderror

    <div class="grid min-h-0 flex-1 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 shadow-2xl lg:grid-cols-[310px_minmax(0,1fr)] xl:grid-cols-[310px_minmax(0,1fr)_320px]">
        <section class="{{ $showConversationOnMobile ? 'hidden lg:flex' : 'flex' }} min-h-0 flex-col border-r border-slate-800 bg-slate-900/70">
            <div class="border-b border-slate-800 p-4">
                <label class="sr-only" for="support-search">Search support conversations</label>
                <input
                    id="support-search"
                    wire:model.live.debounce.350ms="search"
                    type="search"
                    placeholder="Search name, username, reference"
                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-purple-500"
                >

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($isAdmin
                        ? ['open' => 'Open', 'waiting' => 'Waiting', 'unassigned' => 'Unassigned', 'mine' => 'My Chats', 'assigned' => 'Assigned', 'resolved' => 'Resolved', 'all' => 'All']
                        : ['open' => 'Open', 'waiting' => 'Waiting', 'mine' => 'My Chats', 'all' => 'All', 'resolved' => 'Resolved'] as $value => $label)
                        <button
                            type="button"
                            wire:click="$set('filter', '{{ $value }}')"
                            class="rounded-full px-3 py-1 text-xs font-semibold transition {{ $filter === $value ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                @forelse($conversations as $conversation)
                    <button
                        type="button"
                        wire:key="support-conversation-{{ $conversation['id'] }}"
                        wire:click="selectConversation({{ $conversation['id'] }})"
                        class="block w-full border-b border-slate-800 px-4 py-4 text-left transition hover:bg-slate-800/80 {{ $selectedConversationId === $conversation['id'] ? 'bg-purple-500/10' : '' }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-bold text-white">{{ $conversation['player_name'] }}</p>
                                <p class="truncate text-xs text-slate-400">{{ '@'.$conversation['player_username'] }}</p>
                            </div>
                            @if($conversation['unread_count'])
                                <span class="min-w-5 rounded-full bg-purple-600 px-1.5 py-0.5 text-center text-[10px] font-bold text-white">
                                    {{ $conversation['unread_count'] }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-2 truncate text-sm text-slate-300">{{ $conversation['preview'] ?: 'No messages yet' }}</p>
                        <div class="mt-2 flex items-center justify-between gap-2 text-[11px]">
                            <span class="capitalize text-purple-300">
                                {{ $conversation['status'] }}
                                @if($conversation['assigned_name']) · {{ $conversation['assigned_name'] }} @endif
                            </span>
                            <span class="text-slate-500">
                                {{ $conversation['last_message_at'] ? \Illuminate\Support\Carbon::parse($conversation['last_message_at'])->diffForHumans(short: true) : '' }}
                            </span>
                        </div>
                    </button>
                @empty
                    <div class="p-8 text-center text-sm text-slate-400">No support conversations match this view.</div>
                @endforelse
            </div>
        </section>

        <section class="{{ ! $showConversationOnMobile && ! $selectedConversationId ? 'hidden lg:flex' : 'flex' }} min-h-0 min-w-0 flex-col overflow-hidden bg-slate-950">
            @if($selectedConversationId && $selectedConversation)
                @php
                    $isClaimable = in_array(($selectedConversation['status'] ?? null), ['bot', 'waiting'], true) && empty($selectedConversation['assigned_to']);
                    $isResolved = ($selectedConversation['status'] ?? null) === 'resolved';
                    $isAssignedToMe = ($selectedConversation['assigned_to'] ?? null) === auth()->id();
                    $canReply = ! $isResolved && ($isAdmin || $isAssignedToMe || $isClaimable);
                @endphp

                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" wire:click="showConversationList" class="rounded-lg p-2 text-slate-300 hover:bg-slate-800 lg:hidden" aria-label="Back to conversations">←</button>
                        <div class="min-w-0">
                            <h2 class="truncate font-bold text-white">{{ $selectedConversation['player_name'] }}</h2>
                            <p class="truncate text-xs text-slate-400">{{ $selectedConversation['reference'] }} · <span class="capitalize">{{ $selectedConversation['status'] }}</span></p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if($isClaimable)
                            <button type="button" wire:click="takeConversation" wire:loading.attr="disabled" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-500 disabled:opacity-50">Take Chat</button>
                        @endif
                        @if(! $isResolved && ($isAdmin || $isAssignedToMe))
                            <button type="button" wire:click="resolveConversation" wire:loading.attr="disabled" class="rounded-lg border border-slate-600 px-3 py-2 text-xs font-bold text-slate-200 hover:bg-slate-800 disabled:opacity-50">Resolve</button>
                        @endif
                    </div>

                    @if($isAdmin && ! $isResolved)
                        <div class="flex w-full items-center gap-2 border-t border-slate-800 pt-3">
                            <select wire:model="selectedAgentId" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white">
                                <option value="">Select an agent</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent['id'] }}">{{ $agent['name'] }} ({{ '@'.$agent['username'] }})</option>
                                @endforeach
                            </select>
                            @if($isClaimable)
                                <button type="button" wire:click="assignConversation" class="rounded-lg bg-purple-600 px-3 py-2 text-sm font-bold text-white">Assign</button>
                            @elseif(! empty($selectedConversation['assigned_to']))
                                <button type="button" wire:click="reassignConversation" class="rounded-lg bg-purple-600 px-3 py-2 text-sm font-bold text-white">Reassign</button>
                            @endif
                        </div>
                        @error('selectedAgentId') <p class="w-full text-xs text-red-300">{{ $message }}</p> @enderror
                    @endif
                </header>

                @if(isset($playerContext['player']))
                    <details class="border-b border-slate-800 bg-slate-900/60 p-4 xl:hidden">
                        <summary class="cursor-pointer text-sm font-bold text-purple-300">Player context & support events</summary>
                        <div class="mt-3 grid gap-3 text-sm sm:grid-cols-3">
                            <div><p class="text-xs text-slate-500">Username</p><p class="text-white">{{ '@'.$playerContext['player']['username'] }}</p></div>
                            <div><p class="text-xs text-slate-500">Brahma Balance</p><p class="font-bold text-white">${{ $playerContext['player']['brahma_balance'] }}</p></div>
                            <div><p class="text-xs text-slate-500">Pending Items</p><p class="text-white">{{ count($playerContext['brahma_plays']) + count($playerContext['deposits']) + count($playerContext['brahma_deposits']) + count($playerContext['cashouts']) }}</p></div>
                        </div>
                        @if($supportEvents)
                            <div class="mt-4 space-y-2">
                                @foreach($supportEvents as $event)
                                    <div class="flex items-start justify-between gap-3 rounded-lg bg-slate-800 p-3 text-xs">
                                        <div>
                                            <p class="font-semibold text-white">{{ $event['label'] }}</p>
                                            @if($event['summary']) <p class="mt-1 text-slate-300">{{ $event['summary'] }}</p> @endif
                                            <p class="mt-1 capitalize text-slate-500">{{ $event['status'] }}</p>
                                        </div>
                                        @if($event['status'] === 'pending' && $canReply)
                                            <button type="button" wire:click="markEventHandled({{ $event['id'] }})" class="text-emerald-300">Mark handled</button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </details>
                @endif

                <div
                    x-data="{ scroll() { this.$nextTick(() => { this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight }) } }"
                    x-init="scroll(); window.addEventListener('support-inbox-scroll', () => scroll())"
                    class="flex min-h-0 flex-1 flex-col overflow-hidden"
                >
                    <div x-ref="messages" class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                        @foreach($timeline as $chatMessage)
                            <div wire:key="support-message-{{ $chatMessage['id'] }}" class="{{ $chatMessage['sender_type'] === 'player' ? 'flex justify-start' : 'flex justify-end' }}">
                                <div class="max-w-[85%] rounded-2xl px-4 py-3 {{ $chatMessage['sender_type'] === 'player' ? 'bg-slate-800 text-white' : 'bg-purple-600/90 text-white' }}">
                                    <p class="mb-1 text-[11px] font-bold {{ $chatMessage['sender_type'] === 'player' ? 'text-cyan-300' : 'text-purple-100' }}">{{ $chatMessage['display_name'] }}</p>
                                    <p class="whitespace-pre-wrap break-words text-sm">{{ $chatMessage['body'] }}</p>
                                    <p class="mt-1 text-right text-[10px] opacity-60">{{ \Illuminate\Support\Carbon::parse($chatMessage['created_at'])->format('M j, g:i A') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <footer class="shrink-0 border-t border-slate-800 p-4">
                    @if($canReply)
                        <form wire:submit="sendReply" class="flex items-end gap-3">
                            <div class="flex-1">
                                <label class="sr-only" for="staff-reply">Reply to player</label>
                                <textarea id="staff-reply" wire:model="message" rows="2" maxlength="2000" placeholder="Reply as Brahmabull Support Team..." class="w-full resize-none rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white outline-none focus:border-purple-500"></textarea>
                                @error('message') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" wire:loading.attr="disabled" wire:target="sendReply" class="rounded-xl bg-purple-600 px-5 py-3 text-sm font-bold text-white hover:bg-purple-500 disabled:opacity-50">Send</button>
                        </form>
                    @elseif($isResolved)
                        <p class="text-center text-sm text-slate-400">This conversation is resolved and remains read-only.</p>
                    @else
                        <p class="text-center text-sm text-slate-400">This conversation is being handled by another support member.</p>
                    @endif
                </footer>
            @else
                <div class="flex flex-1 items-center justify-center p-8 text-center">
                    <div>
                        <p class="text-lg font-bold text-white">Select a conversation</p>
                        <p class="mt-2 text-sm text-slate-400">Choose a player support request from the Inbox.</p>
                    </div>
                </div>
            @endif
        </section>

        <aside class="{{ $selectedConversationId && $selectedConversation ? 'hidden xl:block' : 'hidden' }} min-h-0 overflow-y-auto border-l border-slate-800 bg-slate-900/60 p-5">
            @if($selectedConversationId && $selectedConversation && isset($playerContext['player']))
                <h3 class="font-bold text-white">Player Context</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Player Name</dt><dd class="text-white">{{ $playerContext['player']['name'] }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Username</dt><dd class="text-white">{{ '@'.$playerContext['player']['username'] }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Player ID</dt><dd class="text-white">#{{ $playerContext['player']['id'] }}</dd></div>
                    <div class="rounded-xl border border-purple-500/30 bg-purple-500/10 p-3"><dt class="text-xs text-purple-200">Brahma Balance</dt><dd class="mt-1 text-2xl font-black text-white">${{ $playerContext['player']['brahma_balance'] }}</dd></div>
                </dl>

                @foreach([
                    'brahma_plays' => 'Pending Brahma Plays',
                    'deposits' => 'Pending Game Deposits',
                    'brahma_deposits' => 'Pending Brahma Deposits',
                    'cashouts' => 'Pending Cashouts',
                    'game_accounts' => 'Recent Game Accounts',
                ] as $key => $heading)
                    <div class="mt-6">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $heading }}</h4>
                        <div class="mt-2 space-y-2">
                            @forelse($playerContext[$key] ?? [] as $item)
                                <div class="rounded-lg bg-slate-800 p-3 text-xs text-slate-200">
                                    <p class="font-semibold">{{ $item['game'] ?? ($item['reference'] ?? 'Request') }}</p>
                                    @isset($item['reference']) <p class="mt-1 text-slate-500">{{ $item['reference'] }}</p> @endisset
                                    @isset($item['amount']) <p class="mt-1 text-purple-300">${{ $item['amount'] }}</p> @endisset
                                    @isset($item['username']) <p class="mt-1 text-slate-400">{{ $item['username'] }}</p> @endisset
                                </div>
                            @empty
                                <p class="text-xs text-slate-600">None</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach

                <div class="mt-6">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Support Events</h4>
                    <div class="mt-2 space-y-2">
                        @forelse($supportEvents as $event)
                            <div class="rounded-lg border border-slate-700 bg-slate-800 p-3 text-xs">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-semibold text-white">{{ $event['label'] }}</p>
                                        @if($event['summary']) <p class="mt-1 text-slate-300">{{ $event['summary'] }}</p> @endif
                                        <p class="mt-1 capitalize text-slate-400">{{ $event['status'] }}</p>
                                    </div>
                                    @if($event['status'] === 'pending' && $canReply)
                                        <button type="button" wire:click="markEventHandled({{ $event['id'] }})" class="text-emerald-300 hover:text-emerald-200">Mark handled</button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-600">No support events.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </aside>
    </div>
</div>
    @endif
</div>
