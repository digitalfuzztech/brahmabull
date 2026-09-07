<div
    x-data="window.BrahmaE2eeDevices(@js([
        'currentUserId' => auth()->id(),
        'registerDeviceUrl' => route('team.e2ee.devices.store'),
        'devicesIndexUrl' => route('team.e2ee.devices.index'),
        'approvalPlanUrl' => route('team.e2ee.devices.approval-plan', '__DEVICE__'),
        'approveUrl' => route('team.e2ee.devices.approve', '__DEVICE__'),
        'revokeUrl' => route('team.e2ee.devices.destroy', '__DEVICE__'),
    ]))"
    x-init="init"
    class="flex h-full min-h-0 flex-col overflow-hidden"
>
    <div class="mb-4 flex shrink-0 flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-white">Team Messenger</h1>
            <p class="mt-1 text-sm text-slate-400">Staff noticeboard, private direct messages, and Agent groups</p>
        </div>
        <div class="flex gap-2">
            <button type="button" x-on:click="show()" class="rounded-xl border border-emerald-500/50 px-4 py-2 text-sm font-bold text-emerald-200 hover:bg-emerald-500/10">Secure Devices</button>
            <button type="button" wire:click="openNewMessage" class="rounded-xl bg-purple-600 px-4 py-2 text-sm font-bold text-white hover:bg-purple-500">New Message</button>
            @if(! $isAdmin)
                <button type="button" wire:click="openCreateGroup" class="rounded-xl border border-slate-600 px-4 py-2 text-sm font-bold text-slate-200 hover:bg-slate-800">New Group</button>
            @endif
        </div>
    </div>

    <div class="grid min-h-0 flex-1 overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 shadow-2xl lg:grid-cols-[310px_minmax(0,1fr)] xl:grid-cols-[310px_minmax(0,1fr)_300px]">
        <section class="{{ $showConversationOnMobile ? 'hidden lg:flex' : 'flex' }} min-h-0 flex-col border-r border-slate-800 bg-slate-900/70">
            <div class="border-b border-slate-800 p-4">
                <input wire:model.live.debounce.350ms="search" type="search" placeholder="Search Team conversations" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-purple-500">
                <div class="mt-3 grid grid-cols-3 gap-2">
                    <button type="button" wire:click="$dispatchTo('admin.team-messenger', 'team-section-selected', { section: 'channels' })" class="rounded-lg px-2 py-2 text-xs font-bold {{ $section === 'channels' ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300' }}">Channels</button>
                    <button type="button" wire:click="$dispatchTo('admin.team-messenger', 'team-section-selected', { section: 'direct' })" class="rounded-lg px-2 py-2 text-xs font-bold {{ $section === 'direct' ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300' }}">Direct</button>
                    <button type="button" wire:click="$dispatchTo('admin.team-messenger', 'team-section-selected', { section: '{{ $isAdmin ? 'oversight' : 'groups' }}' })" class="rounded-lg px-2 py-2 text-xs font-bold {{ in_array($section, ['groups', 'oversight'], true) ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300' }}">{{ $isAdmin ? 'Oversight' : 'Groups' }}</button>
                </div>
            </div>

            <div data-team-conversation-list wire:poll.2s="pollList" class="min-h-0 flex-1 overflow-y-auto">
                @forelse($conversations as $conversation)
                    @php
                        $rowUnread = (int) ($conversation['unread_count'] ?? 0);
                    @endphp
                    <button
                        type="button"

                        wire:key="team-conversation-{{ $conversation['id'] }}"

                        data-team-conversation-id="{{ $conversation['id'] }}"
                        data-unread-count="{{ $rowUnread }}"
                        data-unread="{{ $rowUnread > 0 ? 'true' : 'false' }}"

                        wire:click="selectTeamConversation({{ $conversation['id'] }})"

                        class="
        block w-full
        border-b border-slate-800
        px-4 py-4
        text-left
        transition-colors
        hover:bg-slate-800/80

        {{ $selectedConversationId === $conversation['id']
            ? 'bg-purple-500/5'
            : '' }}

        {{ $rowUnread > 0
            ? 'bg-purple-500/10 ring-1 ring-inset ring-purple-400/20'
            : '' }}
    "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate {{ $rowUnread > 0 ? 'font-black text-white' : 'font-bold text-slate-200' }}">{{ $conversation['name'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $conversation['type'] === 'internal_channel' ? 'Staff channel' : ($conversation['type'] === 'internal_group' ? $conversation['member_count'].' members' : 'Direct message') }}</p>
                            </div>
                            <span data-team-conversation-unread data-unread-count="{{ $rowUnread }}" class="rounded-full bg-purple-600 px-2 py-0.5 text-[10px] font-bold text-white {{ $rowUnread > 0 ? 'inline-flex items-center justify-center' : 'hidden' }}">{{ $rowUnread > 99 ? '99+' : $rowUnread }}</span>
                        </div>
                        <p
                            class="mt-2 truncate text-sm
        {{ $rowUnread > 0
            ? 'font-bold text-white'
            : 'font-normal text-slate-300' }}"
                        >
                            {{ $conversation['preview'] }}
                        </p>
                        <div class="mt-2 flex justify-between text-[11px] text-slate-500">
                            <span>{{ $conversation['is_observer'] ? 'Read-only oversight' : ($conversation['type'] === 'internal_channel' ? 'Noticeboard' : 'Team chat') }}</span>
                            <span>{{ $conversation['last_message_at'] ? \Illuminate\Support\Carbon::parse($conversation['last_message_at'])->diffForHumans(short: true) : '' }}</span>
                        </div>
                    </button>
                @empty
                    <p class="p-8 text-center text-sm text-slate-400">No Team conversations found.</p>
                @endforelse
            </div>
        </section>

        <section class="{{ ! $showConversationOnMobile && ! $selectedConversationId ? 'hidden lg:flex' : 'flex' }} min-h-0 min-w-0 flex-col overflow-hidden">
            @if($selectedConversationId && $details)
                <header class="flex items-center justify-between gap-3 border-b border-slate-800 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" wire:click="showConversationList" class="rounded-lg p-2 text-slate-300 hover:bg-slate-800 lg:hidden" aria-label="Back to Team conversations">←</button>
                        <div class="min-w-0">
                            <h2 class="truncate font-bold text-white">{{ $details['name'] }}</h2>
                            <p class="flex items-center gap-2 text-xs text-slate-400">@if($details['type'] === 'internal_direct')<span class="h-2.5 w-2.5 rounded-full {{ $details['other_online'] ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,.8)]' : 'bg-slate-600' }}"></span>@endif{{ $details['type'] === 'internal_channel' ? 'Staff announcement channel' : ($details['type'] === 'internal_group' ? $details['member_count'].' members' : ($details['other_online'] ? 'Online' : 'Offline')) }}{{ $details['is_observer'] ? ' · Read-only oversight' : '' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if($details['type'] === 'internal_direct' && ! $details['is_e2ee'])
                            <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('team-e2ee-enable'))" class="rounded-lg border border-emerald-500/50 px-3 py-2 text-xs font-bold text-emerald-300 hover:bg-emerald-500/10">Enable End-to-End Encryption</button>
                        @endif
                        @if($details['type'] === 'internal_direct' && $details['is_e2ee'] && $details['e2ee_rotation_required'])
                            <button type="button" x-on:click="window.dispatchEvent(new CustomEvent('team-e2ee-rotate'))" class="rounded-lg border border-amber-500/50 px-3 py-2 text-xs font-bold text-amber-200 hover:bg-amber-500/10">Update Encryption</button>
                        @endif
                        @if($details['type'] === 'internal_direct' && $details['is_e2ee'])
                            <button type="button" wire:click="openDisableE2ee" class="rounded-lg border border-red-500/40 px-3 py-2 text-xs font-bold text-red-200 hover:bg-red-500/10">Disable End-to-End Encryption</button>
                        @endif
                        <button type="button" wire:click="openDetails" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:bg-slate-800">Details</button>
                    </div>
                </header>

                <div
                    wire:key="team-e2ee-conversation-{{ $selectedConversationId }}"
                    wire:poll.3s.visible="pollSelected"
                    x-data="{ ...window.BrahmaE2eeTeam(@js([
                        'currentUserId' => auth()->id(),
                        'details' => $details,
                        'messages' => $teamMessages,
                        'registerDeviceUrl' => route('team.e2ee.devices.store'),
                        'devicesIndexUrl' => route('team.e2ee.devices.index'),
                        'devicesUrl' => route('team.e2ee.conversations.devices', $selectedConversationId),
                        'activateUrl' => route('team.e2ee.conversations.activate', $selectedConversationId),
                        'rotateUrl' => route('team.e2ee.conversations.rotate', $selectedConversationId),
                        'wrappedKeyUrl' => route('team.e2ee.conversations.wrapped-key', $selectedConversationId),
                        'messagesUrl' => route('team.e2ee.conversations.messages.store', $selectedConversationId),
                        'attachmentsUrl' => route('team.e2ee.conversations.attachments.store', $selectedConversationId),
                        'editMessageUrl' => route('team.e2ee.messages.update', '__MESSAGE__'),
                        'reactionUrl' => route('team.e2ee.messages.reactions.store', '__MESSAGE__'),
                    ])), scroll(force = false) { this.$nextTick(() => { const el = this.$refs.messages; if (force || el.scrollHeight - el.scrollTop - el.clientHeight < 180) el.scrollTop = el.scrollHeight }) } }"
                    x-init="scroll(true); window.addEventListener('team-messenger-scroll', event => scroll(event.detail.force))"
                    x-on:team-e2ee-sync.window="sync($event)"
                    x-on:team-e2ee-enable.window="enable"
                    x-on:team-e2ee-rotate.window="rotate"
                    x-on:e2ee-device-status-changed.window="deviceStatusChanged"
                    class="flex min-h-0 flex-1 flex-col overflow-hidden"
                >
                    <p x-show="securityMessage" x-text="securityMessage" class="shrink-0 border-b border-slate-800 bg-slate-900 px-4 py-2 text-center text-xs text-amber-200"></p>
                    @if($details['is_e2ee'] && $details['e2ee_disable_requested_by'])
                        <div class="shrink-0 border-b border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                            @if($details['e2ee_disable_requested_by_me'])
                                <p class="text-center">Awaiting the other participant's approval to disable encryption. New messages remain end-to-end encrypted.</p>
                            @else
                                <div class="flex flex-wrap items-center justify-center gap-3">
                                    <p>A participant requested to disable end-to-end encryption for future messages.</p>
                                    <button type="button" wire:click="keepE2ee" wire:loading.attr="disabled" wire:target="keepE2ee,approveDisableE2ee" class="rounded-lg border border-emerald-500/50 px-3 py-2 text-xs font-bold text-emerald-200 disabled:opacity-50">Keep Encryption</button>
                                    <button type="button" wire:click="approveDisableE2ee" wire:loading.attr="disabled" wire:target="keepE2ee,approveDisableE2ee" class="rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-50">Disable Encryption</button>
                                </div>
                            @endif
                        </div>
                    @endif
                    <div x-ref="messages" class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                        @foreach($teamMessages as $chatMessage)
                            @if($chatMessage['is_e2ee_boundary'])
                                <div wire:key="team-message-{{ $chatMessage['id'] }}" class="py-2 text-center">
                                    <p class="text-xs text-slate-500">Earlier messages were sent before end-to-end encryption was enabled.</p>
                                    <div class="my-2 flex items-center gap-3 text-xs font-bold text-emerald-300"><span class="h-px flex-1 bg-emerald-500/30"></span><span>End-to-end encryption enabled</span><span class="h-px flex-1 bg-emerald-500/30"></span></div>
                                </div>
                            @elseif($chatMessage['is_e2ee_disabled_boundary'])
                                <div wire:key="team-message-{{ $chatMessage['id'] }}" class="py-2 text-center">
                                    <div class="my-2 flex items-center gap-3 text-xs font-bold text-slate-400"><span class="h-px flex-1 bg-slate-600/50"></span><span>End-to-end encryption disabled for new messages</span><span class="h-px flex-1 bg-slate-600/50"></span></div>
                                </div>
                            @else
                            @php $mine = $chatMessage['sender_id'] === auth()->id(); @endphp
                            <div wire:key="team-message-{{ $chatMessage['id'] }}" class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                                <div class="group max-w-[88%] rounded-2xl px-4 py-3 {{ $mine ? 'bg-purple-600/90' : 'bg-slate-800' }} text-white sm:max-w-[75%]">
                                    <div class="mb-1 flex items-center justify-between gap-4">
                                        <p class="text-[11px] font-bold {{ $mine ? 'text-purple-100' : 'text-cyan-300' }}">{{ $chatMessage['sender_name'] }}</p>
                                        @if($details['can_send'] && ! $chatMessage['deleted'])
                                            <div class="flex gap-2 text-[10px] opacity-70 group-hover:opacity-100">
                                                @if($details['can_reply'])
                                                    @if($details['is_e2ee'])<button type="button" x-on:click="setReply(@js($chatMessage))">Reply</button>@else<button type="button" wire:click="setReply({{ $chatMessage['id'] }})">Reply</button>@endif
                                                @endif
                                                @if($chatMessage['can_edit'])
                                                    @if($chatMessage['is_encrypted'])<button type="button" x-on:click="beginEdit(@js($chatMessage))">Edit</button>@else<button type="button" wire:click="startEdit({{ $chatMessage['id'] }})">Edit</button>@endif
                                                @endif
                                                @if($chatMessage['can_delete'])<button type="button" wire:click="openDeleteMessage({{ $chatMessage['id'] }})" class="text-red-200">Delete</button>@endif
                                            </div>
                                        @endif
                                    </div>

                                    @if($chatMessage['reply'])
                                        <div class="mb-2 rounded-lg border-l-2 border-cyan-300 bg-black/20 px-3 py-2 text-xs">
                                            <p class="font-bold text-cyan-200">{{ $chatMessage['reply']['sender_name'] }}</p>
                                            @if($chatMessage['reply']['is_encrypted'])
                                                <p class="mt-1 line-clamp-2 opacity-80" x-text="replyText(@js($chatMessage['reply']))"></p>
                                            @else
                                                <p class="mt-1 line-clamp-2 opacity-80">{{ $chatMessage['reply']['deleted'] ? 'This message was deleted.' : $chatMessage['reply']['body'] }}</p>
                                            @endif
                                        </div>
                                    @endif

                                    @if($chatMessage['deleted'])
                                        <p class="italic text-sm text-slate-300">This message was deleted.</p>
                                    @elseif($chatMessage['is_encrypted'])
                                        <p class="whitespace-pre-wrap break-words text-sm" x-text="textFor(@js($chatMessage))"></p>
                                    @elseif(filled($chatMessage['body']))
                                        <p class="whitespace-pre-wrap break-words text-sm">{{ $chatMessage['body'] }}</p>
                                    @endif

                                    @foreach($chatMessage['attachments'] as $media)
                                        <div class="mt-3 overflow-hidden rounded-xl border border-white/10 bg-black/20">
                                            @if($media['is_encrypted'])
                                                <div class="p-3">
                                                    <template x-if="!attachmentView({{ $media['id'] }}).url">
                                                        <button type="button" x-on:click="loadAttachment(@js($chatMessage), @js($media))" class="w-full rounded-lg border border-emerald-500/30 px-3 py-2 text-left text-xs text-emerald-200">
                                                            <span x-text="attachmentView({{ $media['id'] }}).loading ? 'Decrypting attachment…' : (attachmentView({{ $media['id'] }}).metadata?.original_name || 'Decrypt attachment')"></span>
                                                        </button>
                                                    </template>
                                                    <template x-if="attachmentView({{ $media['id'] }}).url">
                                                        <div>
                                                            <img x-show="attachmentView({{ $media['id'] }}).metadata?.media_type === 'image'" x-bind:src="attachmentView({{ $media['id'] }}).url" alt="Decrypted internal attachment" class="max-h-72 w-full object-contain">
                                                            <video x-show="attachmentView({{ $media['id'] }}).metadata?.media_type === 'video'" x-bind:src="attachmentView({{ $media['id'] }}).url" controls preload="metadata" class="max-h-72 w-full"></video>
                                                            <audio x-show="attachmentView({{ $media['id'] }}).metadata?.media_type === 'audio'" x-bind:src="attachmentView({{ $media['id'] }}).url" controls preload="metadata" class="w-full"></audio>
                                                            <div class="mt-2 flex items-center justify-between gap-3">
                                                                <p class="min-w-0 truncate text-xs" x-text="attachmentView({{ $media['id'] }}).metadata?.original_name"></p>
                                                                <button type="button" x-on:click="downloadAttachment(@js($chatMessage), @js($media))" class="text-xs text-purple-200">Download</button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            @elseif($media['media_type'] === 'image')
                                                <button type="button" wire:click="previewImage({{ $media['id'] }})" class="block w-full">
                                                    <img src="{{ $media['view_url'] }}" alt="{{ $media['original_name'] }}" class="max-h-72 w-full object-contain">
                                                </button>
                                            @elseif($media['media_type'] === 'video')
                                                <video controls preload="metadata" class="max-h-72 w-full"><source src="{{ $media['view_url'] }}" type="{{ $media['mime_type'] }}"></video>
                                            @elseif($media['media_type'] === 'audio')
                                                <audio controls preload="metadata" class="w-full"><source src="{{ $media['view_url'] }}" type="{{ $media['mime_type'] }}"></audio>
                                            @else
                                                <div class="flex items-center justify-between gap-3 p-3">
                                                    <div class="min-w-0"><p class="truncate text-sm font-bold">{{ $media['original_name'] }}</p><p class="text-[10px] opacity-60">{{ number_format($media['file_size'] / 1024, 1) }} KB</p></div>
                                                    <div class="flex gap-2"><a href="{{ $media['view_url'] }}" target="_blank" rel="noopener" class="text-xs text-cyan-200">View</a><a href="{{ $media['download_url'] }}" class="text-xs text-purple-200">Download</a></div>
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    <p class="mt-2 text-right text-[10px] opacity-60">{{ \Illuminate\Support\Carbon::parse($chatMessage['created_at'])->format('M j, g:i A') }}@if($chatMessage['edited_at'] && ! $chatMessage['deleted']) · edited @endif</p>

                                    @if($chatMessage['is_encrypted'])
                                        <div x-show="reactionsFor(@js($chatMessage)).length" class="mt-2 flex flex-wrap gap-1">
                                            <template x-for="summary in reactionsFor(@js($chatMessage))" x-bind:key="summary.reaction">
                                                <span class="rounded-full bg-black/25 px-2 py-0.5 text-xs"><span x-text="summary.reaction"></span> <span x-text="summary.count"></span></span>
                                            </template>
                                        </div>
                                    @elseif($chatMessage['reactions'])
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            @foreach($chatMessage['reactions'] as $summary)
                                                <span class="rounded-full bg-black/25 px-2 py-0.5 text-xs">{{ $summary['reaction'] }} {{ $summary['count'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($details['can_react'] && ! $chatMessage['deleted'] && (! $details['is_e2ee'] || $chatMessage['is_encrypted']))
                                        <div class="mt-2 flex flex-wrap gap-1 border-t border-white/10 pt-2">
                                            @foreach($reactions as $reaction)
                                                @if($chatMessage['is_encrypted'])
                                                    <button type="button" x-on:click="react(@js($chatMessage), @js($reaction))" class="rounded p-1 text-sm hover:bg-white/10">{{ $reaction }}</button>
                                                @else
                                                    <button type="button" wire:click="react({{ $chatMessage['id'] }}, '{{ $reaction }}')" class="rounded p-1 text-sm hover:bg-white/10">{{ $reaction }}</button>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @endif
                        @endforeach
                    </div>

                <footer class="shrink-0 border-t border-slate-800 bg-slate-950 p-4">
                    @if($details['can_send'])
                        @if($details['is_e2ee'])
                            <div x-show="details.e2ee_rotation_required" class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-3 text-center text-sm text-amber-100">
                                <p>Encryption key update required before sending new messages.</p>
                                <button type="button" x-on:click="rotate" x-bind:disabled="busy" class="mt-2 rounded-lg bg-amber-500 px-4 py-2 text-xs font-bold text-slate-950 disabled:opacity-50">Update Encryption</button>
                            </div>
                            <div x-show="!details.e2ee_rotation_required && editingId" class="rounded-xl border border-purple-500/40 bg-slate-900 p-3">
                                <div class="mb-2 flex justify-between"><span class="text-xs font-bold text-purple-300">Edit encrypted message</span><button type="button" x-on:click="cancelEdit" class="text-xs text-slate-400">Cancel</button></div>
                                <div class="flex items-end gap-2"><textarea x-model="editText" rows="2" maxlength="2000" class="min-w-0 flex-1 resize-none rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"></textarea><button type="button" x-on:click="saveEdit(messageById(editingId))" x-bind:disabled="busy" class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-bold disabled:opacity-50">Save</button></div>
                            </div>
                            <div x-show="!details.e2ee_rotation_required && !editingId">
                                <div x-show="replyToId" class="mb-2 flex items-start justify-between rounded-lg border-l-2 border-cyan-400 bg-slate-900 p-3 text-xs">
                                    <div><p class="font-bold text-cyan-300">Replying to encrypted message</p><p class="mt-1 line-clamp-1 text-slate-400" x-text="replyText(messageById(replyToId))"></p></div>
                                    <button type="button" x-on:click="replyToId = null" class="text-slate-400">×</button>
                                </div>
                                <div x-show="selectedFile" class="mb-2 flex items-center justify-between rounded-lg bg-slate-900 p-3 text-xs text-slate-300">
                                    <span class="truncate" x-text="selectedFile?.name"></span>
                                    <button type="button" x-on:click="clearAttachment" class="text-red-300">Remove</button>
                                </div>
                                <div data-e2ee-composer-row class="flex flex-nowrap items-end gap-2">
                                    <label class="m-0 flex h-12 w-12 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-emerald-500/40 text-emerald-200 hover:bg-slate-800" title="Attach one encrypted file (1,750 KiB maximum)">
                                        <span aria-hidden="true">＋</span><span class="sr-only">Attach encrypted file</span>
                                        <input x-ref="encryptedAttachment" x-on:change="chooseAttachment($event)" type="file" class="hidden" accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mp3,.m4a,.wav,.ogg,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                    </label>
                                    <textarea
                                        x-ref="teamComposer"

                                        x-model="draft"

                                        x-on:pointerdown="$wire.markSelectedConversationRead()"

                                        x-on:input.debounce.500ms="$wire.markSelectedConversationRead()"

                                        x-on:keydown.enter="
        if (!$event.shiftKey && !$event.isComposing) {
            $event.preventDefault();
            send()
        }
    "

                                        rows="2"
                                        maxlength="2000"
                                        placeholder="Encrypted message {{ $details['name'] }}..."
                                        class="m-0 block h-12 min-h-12 min-w-0 flex-1 resize-none box-border overflow-y-auto rounded-xl border border-emerald-500/40 bg-slate-900 px-3 py-2 text-sm leading-7 text-white outline-none focus:border-emerald-400"
                                    ></textarea>
                                   <button type="button" x-on:click="send" x-bind:disabled="busy || (!draft.trim() && !selectedFile)" class="m-0 flex h-12 shrink-0 items-center justify-center rounded-xl bg-emerald-600 px-5 text-sm font-bold text-white disabled:opacity-50"><span x-text="busy ? 'Encrypting…' : 'Send'"></span></button>
                                </div>
                            </div>
                        @else
                        @if($editingMessageId)
                            <form wire:submit="saveEdit" class="rounded-xl border border-purple-500/40 bg-slate-900 p-3">
                                <div class="mb-2 flex justify-between"><span class="text-xs font-bold text-purple-300">Edit message</span><button type="button" wire:click="cancelEdit" class="text-xs text-slate-400">Cancel</button></div>
                                <div class="flex items-end gap-2"><textarea wire:model="editingMessage" rows="2" maxlength="2000" class="min-w-0 flex-1 resize-none rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm"></textarea><button type="submit" class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-bold">Save</button></div>
                                @error('editingMessage')<p class="mt-1 text-xs text-red-300">{{ $message }}</p>@enderror
                            </form>
                        @else
                        @if($replyToMessageId)
                            @php $replying = collect($teamMessages)->firstWhere('id', $replyToMessageId); @endphp
                            <div class="mb-2 flex items-start justify-between rounded-lg border-l-2 border-cyan-400 bg-slate-900 p-3 text-xs">
                                <div><p class="font-bold text-cyan-300">Replying to {{ $replying['sender_name'] ?? 'message' }}</p><p class="mt-1 line-clamp-1 text-slate-400">{{ $replying['body'] ?? 'Attachment' }}</p></div>
                                <button type="button" wire:click="cancelReply" class="text-slate-400">×</button>
                            </div>
                        @endif
                        @if($attachment)
                            <div class="mb-2 flex items-center justify-between rounded-lg bg-slate-900 p-3 text-xs text-slate-300"><span class="truncate">{{ $attachment->getClientOriginalName() }}</span><button type="button" wire:click="removeAttachment" class="text-red-300">Remove</button></div>
                        @endif
                        <form wire:submit="sendTeamMessage">
                            <div data-plaintext-composer-row class="flex flex-nowrap items-end gap-2">
                                <label class="m-0 flex h-12 w-12 shrink-0 cursor-pointer items-center justify-center rounded-xl border border-slate-700 text-slate-300 hover:bg-slate-800" title="Attach one file (2 MB maximum)">
                                    <span aria-hidden="true">＋</span><span class="sr-only">Attach file</span>
                                    <input wire:model="attachment" type="file" class="hidden" accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mp3,.m4a,.wav,.ogg,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                </label>
                                <textarea
                                    x-ref="teamComposer"
                                    wire:model="message"

                                    x-on:pointerdown="$wire.markSelectedConversationRead()"

                                    x-on:input.debounce.500ms="$wire.markSelectedConversationRead()"

                                    x-on:keydown.enter="
        if (!$event.shiftKey && !$event.isComposing) {
            $event.preventDefault();
            $wire.sendTeamMessage()
        }
    "

                                    rows="2"
                                    maxlength="2000"
                                    placeholder="Message {{ $details['name'] }}..."
                                    class="m-0 block h-12 min-h-12 min-w-0 flex-1 resize-none box-border overflow-y-auto rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm leading-7 text-white outline-none focus:border-purple-500"
                                ></textarea>
                                <button type="submit" wire:loading.attr="disabled" wire:target="sendTeamMessage,attachment" class="m-0 flex h-12 shrink-0 items-center justify-center rounded-xl bg-purple-600 px-5 text-sm font-bold text-white disabled:opacity-50"><span wire:loading.remove wire:target="sendTeamMessage">Send</span><span wire:loading wire:target="sendTeamMessage">Sending…</span></button>
                            </div>
                            @error('message') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                            @error('attachment') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                        </form>
                        @endif
                        @endif
                    @else
                        <p class="text-center text-sm text-slate-400">{{ $details['type'] === 'internal_channel' ? 'This channel is read-only for Agents.' : 'Read-only group oversight. Admin is not a participant.' }}</p>
                    @endif
                </footer>
                </div>
            @else
                <div class="flex flex-1 items-center justify-center p-8 text-center"><div><p class="text-lg font-bold text-white">Select a Team conversation</p><p class="mt-2 text-sm text-slate-400">Open a direct message or internal group.</p></div></div>
            @endif
        </section>

        <aside class="hidden min-h-0 overflow-y-auto border-l border-slate-800 bg-slate-900/60 p-5 xl:block">
            @if($selectedConversationId && $details)
                <h3 class="font-bold text-white">{{ $details['type'] === 'internal_channel' ? 'Channel Details' : ($details['type'] === 'internal_group' ? 'Group Details' : 'Direct Details') }}</h3>
                <p class="mt-1 text-sm text-slate-400">{{ $details['name'] }}</p>
                <div class="mt-5 space-y-2">
                    @foreach($details['members'] as $member)
                        <div class="rounded-lg bg-slate-800 p-3 text-sm"><p class="font-bold text-white">{{ $member['name'] }}</p><p class="text-xs text-slate-400">{{ '@'.$member['username'] }}{{ $member['role'] === 'owner' ? ' · Owner' : '' }}</p></div>
                    @endforeach
                </div>
                <button type="button" wire:click="openDetails" class="mt-5 w-full rounded-lg border border-slate-700 py-2 text-sm text-slate-300">Open Details</button>
            @endif
        </aside>
    </div>

    @if($showNewMessage)
        <div class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/70 p-4" wire:click.self="$set('showNewMessage', false)">
            <div class="max-h-[80vh] w-full max-w-md overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5">
                <div class="flex justify-between"><h3 class="font-bold text-white">New Direct Message</h3><button type="button" wire:click="$set('showNewMessage', false)">×</button></div>
                <input wire:model.live.debounce.300ms="contactSearch" type="search" placeholder="Search staff" class="mt-4 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                <div class="mt-3 space-y-2">@foreach($contacts as $contact)<button type="button" wire:click="startDirect({{ $contact['id'] }})" class="w-full rounded-xl bg-slate-800 p-3 text-left"><span class="font-bold text-white">{{ $contact['name'] }}</span><span class="ml-2 text-xs text-slate-400">{{ $contact['role'] }} · {{ '@'.$contact['username'] }}</span></button>@endforeach</div>
            </div>
        </div>
    @endif

    @if($showDisableE2eeModal)
        <div class="fixed inset-0 z-[10001]" x-data x-on:keydown.escape.window="$wire.cancelDisableE2ee()">
            <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" wire:click="cancelDisableE2ee"></div>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="w-full max-w-md rounded-3xl border border-slate-700 bg-slate-900 p-7 shadow-2xl">
                    <h3 class="text-xl font-black text-white">Disable end-to-end encryption?</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-300">Disabling end-to-end encryption means new messages in this conversation will be stored as normal server-readable chat messages. Existing encrypted messages will remain encrypted.</p>
                    <div class="mt-6 flex gap-3">
                        <button type="button" wire:click="cancelDisableE2ee" class="flex-1 rounded-xl border border-slate-600 py-3 font-bold text-slate-200">Cancel</button>
                        <button type="button" wire:click="requestDisableE2ee" wire:loading.attr="disabled" wire:target="requestDisableE2ee" class="flex-1 rounded-xl bg-red-600 py-3 font-bold text-white disabled:opacity-50"><span wire:loading.remove wire:target="requestDisableE2ee">Request Disable</span><span wire:loading wire:target="requestDisableE2ee">Requesting&hellip;</span></button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($pendingDeleteMessageId)
        <div class="fixed inset-0 z-[10002]" x-data x-on:keydown.escape.window="$wire.cancelDeleteMessage()">
            <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" wire:click="cancelDeleteMessage"></div>
            <div class="fixed inset-0 flex items-center justify-center p-4">
                <div class="w-full max-w-sm rounded-3xl border border-slate-700 bg-slate-900 p-8 shadow-2xl">
                    <h3 class="text-xl font-black text-white">Delete message?</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-300">Are you sure you want to delete this message? This action cannot be undone.</p>
                    <div class="mt-6 flex gap-3">
                        <button type="button" wire:click="cancelDeleteMessage" class="flex-1 rounded-xl border border-slate-600 py-3 font-bold text-slate-200">Cancel</button>
                        <button type="button" wire:click="confirmDeleteMessage" wire:loading.attr="disabled" wire:target="confirmDeleteMessage" class="flex-1 rounded-xl bg-red-600 py-3 font-bold text-white disabled:opacity-50"><span wire:loading.remove wire:target="confirmDeleteMessage">Delete Message</span><span wire:loading wire:target="confirmDeleteMessage">Deleting&hellip;</span></button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div x-cloak x-show="open" class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/70 p-4" x-on:click.self="open = false">
        <div class="max-h-[85dvh] w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-700 bg-slate-950 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                <div><h3 class="font-bold text-white">Secure Chat Devices</h3><p class="text-xs text-slate-400">Approve and revoke only your own cryptographic devices.</p></div>
                <button type="button" x-on:click="open = false" class="text-xl text-slate-400">×</button>
            </div>
            <p x-show="error" x-text="error" class="m-4 rounded-lg border border-red-500/30 bg-red-500/10 p-3 text-sm text-red-200"></p>
            <div class="max-h-[65dvh] space-y-3 overflow-y-auto p-4">
                <p x-show="loading" class="py-8 text-center text-sm text-slate-400">Loading secure devices&hellip;</p>
                <div x-show="!loading && currentDeviceAwaitingApproval()" class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100">
                    <p class="font-bold">This device is awaiting approval.</p>
                    <p x-show="hasTrustedApprover()" class="mt-1 text-xs text-amber-200/80">Open Secure Devices from one of your trusted devices to approve this browser.</p>
                    <p x-show="!hasTrustedApprover()" class="mt-1 text-xs text-amber-200/80">No active trusted device is listed. This browser cannot approve itself; contact the account owner before changing secure-device state.</p>
                </div>
                <template x-for="device in devices" x-bind:key="device.id">
                    <div class="rounded-xl border border-slate-800 bg-slate-900 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2"><p class="font-bold text-white" x-text="device.device_name || 'Secure browser device'"></p><span x-show="isCurrent(device)" class="rounded-full bg-cyan-500/15 px-2 py-0.5 text-[10px] font-bold text-cyan-300">This device</span></div>
                                <p class="mt-2 break-all font-mono text-xs text-slate-400"><span class="font-sans text-slate-500">Fingerprint: </span><span x-text="fingerprint(device.key_fingerprint)"></span></p>
                                <p class="mt-2 text-xs text-slate-500">Created <span x-text="date(device.created_at)"></span> · Last used <span x-text="date(device.last_used_at)"></span></p>
                            </div>
                            <span class="rounded-full px-2 py-1 text-xs font-bold" x-bind:class="device.revoked_at ? 'bg-red-500/15 text-red-300' : (device.trusted_at ? 'bg-emerald-500/15 text-emerald-300' : 'bg-amber-500/15 text-amber-200')" x-text="status(device)"></span>
                        </div>
                        <div x-show="!device.revoked_at" class="mt-3 flex justify-end gap-2">
                            <button x-show="canApprove(device)" type="button" x-on:click="approve(device)" x-bind:disabled="busy" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold disabled:opacity-50">Approve Device</button>
                            <button type="button" x-on:click="revoke(device)" x-bind:disabled="busy" class="rounded-lg border border-red-500/40 px-3 py-2 text-xs font-bold text-red-200 disabled:opacity-50">Revoke</button>
                        </div>
                    </div>
                </template>
                <p x-show="!loading && !error && !devices.length" class="py-8 text-center text-sm text-slate-400">No secure devices registered.</p>
            </div>
            <div class="border-t border-slate-800 px-5 py-3 text-xs text-slate-500">Newly approved devices receive current conversation keys; some older encrypted messages may remain unavailable. A revoked device may retain data it already downloaded. No recovery or Admin escrow key exists.</div>
        </div>
    </div>

    @if($showCreateGroup)
        <div class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/70 p-4" wire:click.self="$set('showCreateGroup', false)">
            <div class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5">
                <div class="flex justify-between"><h3 class="font-bold text-white">Create Agent Group</h3><button type="button" wire:click="$set('showCreateGroup', false)">×</button></div>
                <input wire:model="groupName" maxlength="100" placeholder="Group name" class="mt-4 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white">
                @error('groupName') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                <p class="mt-4 text-xs text-slate-400">Select at least two other Agents.</p>
                <div class="mt-2 space-y-2">@foreach($groupCandidates as $candidate)<label class="flex items-center gap-3 rounded-lg bg-slate-800 p-3"><input wire:model="selectedMemberIds" type="checkbox" value="{{ $candidate['id'] }}"><span>{{ $candidate['name'] }} <small class="text-slate-400">{{ '@'.$candidate['username'] }}</small></span></label>@endforeach</div>
                @error('selectedMemberIds') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                <button type="button" wire:click="createGroup" class="mt-4 w-full rounded-xl bg-purple-600 py-3 font-bold text-white">Create Group</button>
            </div>
        </div>
    @endif

    @if($showDetails && $details)
        <div class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/70 p-4" wire:click.self="$set('showDetails', false)">
            <div class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-700 bg-slate-900 p-5">
                <div class="flex justify-between"><h3 class="font-bold text-white">Conversation Details</h3><button type="button" wire:click="$set('showDetails', false)">×</button></div>
                <div class="mt-4 space-y-2">@foreach($details['members'] as $member)<div class="flex items-center justify-between rounded-lg bg-slate-800 p-3"><div><p class="font-bold">{{ $member['name'] }}</p><p class="text-xs text-slate-400">{{ '@'.$member['username'] }}{{ $member['role'] === 'owner' ? ' · Owner' : '' }}</p></div>@if($details['is_owner'] && $member['id'] !== auth()->id())<button type="button" wire:click="removeMember({{ $member['id'] }})" class="text-xs text-red-300">Remove</button>@endif</div>@endforeach</div>
                @if($details['type'] === 'internal_group' && $details['is_owner'])
                    <div class="mt-5 border-t border-slate-700 pt-4"><label class="text-xs text-slate-400">Rename Group</label><div class="mt-1 flex gap-2"><input wire:model="renamedGroup" class="min-w-0 flex-1 rounded-lg bg-slate-950 px-3 py-2"><button type="button" wire:click="renameGroup" class="rounded-lg bg-purple-600 px-3">Save</button></div></div>
                    @if($groupCandidates)<div class="mt-4"><label class="text-xs text-slate-400">Add Agent</label><div class="mt-1 flex gap-2"><select wire:model="memberToAdd" class="min-w-0 flex-1 rounded-lg bg-slate-950 px-3 py-2"><option value="">Select Agent</option>@foreach($groupCandidates as $candidate)<option value="{{ $candidate['id'] }}">{{ $candidate['name'] }}</option>@endforeach</select><button type="button" wire:click="addMember" class="rounded-lg bg-emerald-600 px-3">Add</button></div></div>@endif
                @endif
                @if($details['type'] === 'internal_group' && ! $details['is_observer'])<button type="button" wire:click="leaveGroup" class="mt-5 w-full rounded-lg border border-red-500/40 py-2 text-red-300">Leave Group</button>@endif
            </div>
        </div>
    @endif

    @if($previewImageUrl)
        <div class="fixed inset-0 z-[10001] flex items-center justify-center bg-black/90 p-4" wire:click.self="closeImagePreview">
            <button type="button" wire:click="closeImagePreview" class="absolute right-5 top-5 rounded-full bg-slate-800 px-4 py-2 text-white">×</button>
            <img src="{{ $previewImageUrl }}" alt="Internal attachment preview" class="max-h-[90vh] max-w-[95vw] object-contain">
        </div>
    @endif
</div>
