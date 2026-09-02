<div
    @if($selectedConversationId)
        wire:poll.3s.visible="pollTeam"
    @else
        wire:poll.5s.visible="pollTeam"
    @endif
    class="flex h-full min-h-0 flex-col overflow-hidden"
>
    <div class="mb-4 flex shrink-0 flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-white">Team Messenger</h1>
            <p class="mt-1 text-sm text-slate-400">Staff noticeboard, private direct messages, and Agent groups</p>
        </div>
        <div class="flex gap-2">
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
                    <button type="button" wire:click="$set('section', 'channels')" class="rounded-lg px-2 py-2 text-xs font-bold {{ $section === 'channels' ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300' }}">Channels</button>
                    <button type="button" wire:click="$set('section', 'direct')" class="rounded-lg px-2 py-2 text-xs font-bold {{ $section === 'direct' ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300' }}">Direct</button>
                    <button type="button" wire:click="$set('section', '{{ $isAdmin ? 'oversight' : 'groups' }}')" class="rounded-lg px-2 py-2 text-xs font-bold {{ in_array($section, ['groups', 'oversight'], true) ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300' }}">{{ $isAdmin ? 'Oversight' : 'Groups' }}</button>
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">
                @forelse($conversations as $conversation)
                    <button type="button" wire:key="team-conversation-{{ $conversation['id'] }}" wire:click="selectTeamConversation({{ $conversation['id'] }})" class="block w-full border-b border-slate-800 px-4 py-4 text-left hover:bg-slate-800/80 {{ $selectedConversationId === $conversation['id'] ? 'bg-purple-500/10' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-bold text-white">{{ $conversation['name'] }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $conversation['type'] === 'internal_channel' ? 'Permanent staff channel' : ($conversation['type'] === 'internal_group' ? $conversation['member_count'].' members' : 'Direct message') }}</p>
                            </div>
                            @if($conversation['unread_count'])
                                <span class="rounded-full bg-purple-600 px-2 py-0.5 text-[10px] font-bold text-white">{{ $conversation['unread_count'] }}</span>
                            @endif
                        </div>
                        <p class="mt-2 truncate text-sm text-slate-300">{{ $conversation['preview'] }}</p>
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
                            <p class="flex items-center gap-2 text-xs text-slate-400">@if($details['type'] === 'internal_direct')<span class="h-2.5 w-2.5 rounded-full {{ $details['other_online'] ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,.8)]' : 'bg-slate-600' }}"></span>@endif{{ $details['type'] === 'internal_channel' ? 'Permanent staff noticeboard' : ($details['type'] === 'internal_group' ? $details['member_count'].' members' : ($details['other_online'] ? 'Online' : 'Offline')) }}{{ $details['is_observer'] ? ' · Read-only oversight' : '' }}</p>
                        </div>
                    </div>
                    <button type="button" wire:click="openDetails" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:bg-slate-800">Details</button>
                </header>

                <div x-data="{ scroll(force = false) { this.$nextTick(() => { const el = this.$refs.messages; if (force || el.scrollHeight - el.scrollTop - el.clientHeight < 180) el.scrollTop = el.scrollHeight }) } }" x-init="scroll(true); window.addEventListener('team-messenger-scroll', event => scroll(event.detail.force))" class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div x-ref="messages" class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                        @foreach($teamMessages as $chatMessage)
                            @php $mine = $chatMessage['sender_id'] === auth()->id(); @endphp
                            <div wire:key="team-message-{{ $chatMessage['id'] }}" class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                                <div class="group max-w-[88%] rounded-2xl px-4 py-3 {{ $mine ? 'bg-purple-600/90' : 'bg-slate-800' }} text-white sm:max-w-[75%]">
                                    <div class="mb-1 flex items-center justify-between gap-4">
                                        <p class="text-[11px] font-bold {{ $mine ? 'text-purple-100' : 'text-cyan-300' }}">{{ $chatMessage['sender_name'] }}</p>
                                        @if($details['can_send'] && ! $chatMessage['deleted'])
                                            <div class="flex gap-2 text-[10px] opacity-70 group-hover:opacity-100">
                                                @if($details['can_reply'])<button type="button" wire:click="setReply({{ $chatMessage['id'] }})">Reply</button>@endif
                                                @if($chatMessage['can_edit'])<button type="button" wire:click="startEdit({{ $chatMessage['id'] }})">Edit</button>@endif
                                                @if($chatMessage['can_delete'])<button type="button" wire:click="deleteMessage({{ $chatMessage['id'] }})" wire:confirm="Delete this message for everyone?" class="text-red-200">Delete</button>@endif
                                            </div>
                                        @endif
                                    </div>

                                    @if($chatMessage['reply'])
                                        <div class="mb-2 rounded-lg border-l-2 border-cyan-300 bg-black/20 px-3 py-2 text-xs">
                                            <p class="font-bold text-cyan-200">{{ $chatMessage['reply']['sender_name'] }}</p>
                                            <p class="mt-1 line-clamp-2 opacity-80">{{ $chatMessage['reply']['body'] }}</p>
                                        </div>
                                    @endif

                                    @if($chatMessage['deleted'])
                                        <p class="italic text-sm text-slate-300">This message was deleted.</p>
                                    @elseif(filled($chatMessage['body']))
                                        <p class="whitespace-pre-wrap break-words text-sm">{{ $chatMessage['body'] }}</p>
                                    @endif

                                    @foreach($chatMessage['attachments'] as $media)
                                        <div class="mt-3 overflow-hidden rounded-xl border border-white/10 bg-black/20">
                                            @if($media['media_type'] === 'image')
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

                                    @if($chatMessage['reactions'])
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            @foreach($chatMessage['reactions'] as $summary)
                                                <span class="rounded-full bg-black/25 px-2 py-0.5 text-xs">{{ $summary['reaction'] }} {{ $summary['count'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    @if($details['can_react'] && ! $chatMessage['deleted'])
                                        <div class="mt-2 flex flex-wrap gap-1 border-t border-white/10 pt-2">
                                            @foreach($reactions as $reaction)
                                                <button type="button" wire:click="react({{ $chatMessage['id'] }}, '{{ $reaction }}')" class="rounded p-1 text-sm hover:bg-white/10">{{ $reaction }}</button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <footer class="shrink-0 border-t border-slate-800 bg-slate-950 p-4">
                    @if($details['can_send'])
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
                        <form wire:submit="sendTeamMessage" class="flex items-end gap-2">
                            <label class="cursor-pointer rounded-xl border border-slate-700 p-3 text-slate-300 hover:bg-slate-800" title="Attach one file (2 MB maximum)">
                                <span aria-hidden="true">＋</span><span class="sr-only">Attach file</span>
                                <input wire:model="attachment" type="file" class="hidden" accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mp3,.m4a,.wav,.ogg,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                            </label>
                            <div class="min-w-0 flex-1">
                                <textarea wire:model="message" x-on:keydown.enter="if (!$event.shiftKey && !$event.isComposing) { $event.preventDefault(); $wire.sendTeamMessage() }" rows="2" maxlength="2000" placeholder="Message {{ $details['name'] }}..." class="w-full resize-none rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-white outline-none focus:border-purple-500"></textarea>
                                @error('message') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                                @error('attachment') <p class="mt-1 text-xs text-red-300">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" wire:loading.attr="disabled" wire:target="sendTeamMessage,attachment" class="rounded-xl bg-purple-600 px-5 py-3 text-sm font-bold text-white disabled:opacity-50"><span wire:loading.remove wire:target="sendTeamMessage">Send</span><span wire:loading wire:target="sendTeamMessage">Sending…</span></button>
                        </form>
                        @endif
                    @else
                        <p class="text-center text-sm text-slate-400">{{ $details['type'] === 'internal_channel' ? 'The noticeboard is read-only for Agents.' : 'Read-only group oversight. Admin is not a participant.' }}</p>
                    @endif
                </footer>
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
