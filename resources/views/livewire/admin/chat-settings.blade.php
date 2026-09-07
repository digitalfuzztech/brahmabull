<div class="space-y-6 text-slate-100">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold">Chat Settings</h1><p class="text-sm text-slate-400">Manage the existing support bot and internal staff channels.</p></div>
        <input wire:model.live.debounce.300ms="search" class="rounded-xl border border-slate-700 bg-slate-900 px-4 py-2" placeholder="Search settings...">
    </div>
    @if(session('success'))<div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-emerald-200">{{ session('success') }}</div>@endif
    <div class="flex gap-2 border-b border-slate-800">
        <button wire:click="$set('tab','chatbot')" class="px-4 py-3 font-bold {{ $tab === 'chatbot' ? 'border-b-2 border-purple-400 text-purple-300' : 'text-slate-400' }}">Chatbot</button>
        <button wire:click="$set('tab','channels')" class="px-4 py-3 font-bold {{ $tab === 'channels' ? 'border-b-2 border-purple-400 text-purple-300' : 'text-slate-400' }}">Channels</button>
    </div>

    @if($tab === 'chatbot')
        <section class="grid gap-5 xl:grid-cols-2">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="text-lg font-bold">Main Menu</h2>
                <div class="mt-4 space-y-2">
                    @foreach($menuItems as $item)
                        <div class="flex items-center gap-3 rounded-xl bg-slate-800 p-3" wire:key="menu-{{ $item->id }}">
                            <span class="w-8 text-xs text-slate-500">{{ $item->sort_order }}</span><div class="min-w-0 flex-1"><p class="font-semibold">{{ $item->label }}</p><p class="truncate text-xs text-slate-400">{{ $item->action_type }} · {{ $item->is_active ? 'Enabled' : 'Disabled' }}</p></div>
                            <button wire:click="editMenu({{ $item->id }})" class="text-xs text-purple-300">Edit</button>
                            <button wire:click="toggleMenu({{ $item->id }})" class="text-xs text-amber-300">{{ $item->is_active ? 'Disable' : 'Enable' }}</button>
                            <button wire:click="deleteMenu({{ $item->id }})" wire:confirm="Delete this Main Menu item?" class="text-xs text-red-300">Delete</button>
                        </div>
                    @endforeach
                </div>
                <form wire:submit="saveMenu" class="mt-5 grid gap-3 sm:grid-cols-2">
                    <input wire:model="menuLabel" class="rounded-lg border border-slate-700 bg-slate-950 p-2" placeholder="Menu label">
                    <select wire:model="menuAction" class="rounded-lg border border-slate-700 bg-slate-950 p-2">@foreach($safeActions as $action)<option value="{{ $action }}">{{ str($action)->replace('_',' ')->title() }}</option>@endforeach</select>
                    <input wire:model="menuOrder" type="number" class="rounded-lg border border-slate-700 bg-slate-950 p-2" placeholder="Order">
                    <label class="flex items-center gap-2"><input wire:model="menuActive" type="checkbox"> Enabled</label>
                    <textarea wire:model="menuResponse" class="sm:col-span-2 rounded-lg border border-slate-700 bg-slate-950 p-2" placeholder="Optional response text"></textarea>
                    <div class="sm:col-span-2 flex gap-2"><button class="rounded-lg bg-purple-600 px-4 py-2 font-bold">{{ $menuId ? 'Update' : 'Add' }} Menu Item</button>@if($menuId)<button type="button" wire:click="resetMenuForm" class="rounded-lg bg-slate-700 px-4 py-2">Cancel</button>@endif</div>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="text-lg font-bold">Side-effect-free Preview</h2>
                <p class="mt-1 text-xs text-slate-400">Matches rules only. It never sends messages, reminders, or notifications.</p>
                <div class="mt-4 flex gap-2"><input wire:model="previewInput" class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 p-2" placeholder="My cashout is still pending"><button wire:click="preview" class="rounded-lg bg-indigo-600 px-4 py-2 font-bold">Test</button></div>
                @if($previewResult)
                    <div class="mt-4 rounded-xl bg-slate-800 p-4 text-sm">@if($previewResult['matched'])<p><b>Matched rule:</b> {{ $previewResult['name'] }}</p><p><b>Trigger:</b> {{ $previewResult['trigger_type'] }}</p><p><b>Response:</b> {{ $previewResult['response_text'] ?: '—' }}</p><p><b>Action:</b> {{ $previewResult['action_type'] }}</p>@else<p>No database rule matched.</p>@endif</div>
                @endif
            </div>
        </section>

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="text-lg font-bold">Rules</h2>
            <div class="mt-4 grid gap-3 lg:grid-cols-2">
                @foreach($rules as $rule)
                    <article class="rounded-xl bg-slate-800 p-4" wire:key="rule-{{ $rule->id }}"><div class="flex justify-between gap-3"><div><h3 class="font-bold">{{ $rule->name }}</h3><p class="text-xs text-slate-400">{{ $rule->trigger_type }} · priority {{ $rule->priority }} · {{ $rule->is_active ? 'Active' : 'Disabled' }}</p></div><span class="text-xs text-purple-300">{{ $rule->action_type ?? 'none' }}</span></div><p class="mt-2 text-xs text-slate-300">{{ implode(' · ', $rule->trigger_value ?? []) ?: 'Fallback' }}</p><p class="mt-2 text-sm text-slate-400">{{ $rule->response_text }}</p><div class="mt-3 flex gap-3"><button wire:click="editRule({{ $rule->id }})" class="text-xs text-purple-300">Edit</button><button wire:click="toggleRule({{ $rule->id }})" class="text-xs text-amber-300">{{ $rule->is_active ? 'Disable' : 'Enable' }}</button><button wire:click="deleteRule({{ $rule->id }})" wire:confirm="Do you really want to delete this chatbot rule?" class="text-xs text-red-300">Delete</button></div></article>
                @endforeach
            </div>
            <form wire:submit="saveRule" class="mt-6 grid gap-3 md:grid-cols-2">
                <input wire:model="ruleName" class="rounded-lg border border-slate-700 bg-slate-950 p-2" placeholder="Rule name">
                <select wire:model="triggerType" class="rounded-lg border border-slate-700 bg-slate-950 p-2"><option value="exact">Exact phrases</option><option value="keyword">Keywords</option><option value="option">Option</option><option value="fallback">Fallback</option></select>
                <textarea wire:model="triggerTerms" class="rounded-lg border border-slate-700 bg-slate-950 p-2" placeholder="One phrase or keyword per line"></textarea>
                <textarea wire:model="responseText" class="rounded-lg border border-slate-700 bg-slate-950 p-2" placeholder="Response text"></textarea>
                <select wire:model="ruleAction" class="rounded-lg border border-slate-700 bg-slate-950 p-2">@foreach($safeActions as $action)<option value="{{ $action }}">{{ str($action)->replace('_',' ')->title() }}</option>@endforeach</select>
                <input wire:model="priority" type="number" class="rounded-lg border border-slate-700 bg-slate-950 p-2" placeholder="Priority">
                <label class="flex items-center gap-2"><input wire:model="ruleActive" type="checkbox"> Active</label>
                <div class="flex gap-2"><button class="rounded-lg bg-purple-600 px-4 py-2 font-bold">{{ $ruleId ? 'Update Rule' : 'Add Rule' }}</button>@if($ruleId)<button type="button" wire:click="resetRuleForm" class="rounded-lg bg-slate-700 px-4 py-2">Cancel</button>@endif</div>
            </form>
            @error('triggerTerms')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
        </section>
    @else
        <section class="grid gap-5 xl:grid-cols-[1.2fr_.8fr]">
            <div class="space-y-3">
                @foreach($channels as $channel)
                    @php($protected = $channel->channel_key === \App\Services\Chat\BrahmaNoticeboardService::CHANNEL_KEY)
                    <article class="rounded-2xl border border-slate-800 bg-slate-900 p-5" wire:key="channel-{{ $channel->id }}"><div class="flex flex-wrap justify-between gap-3"><div><h2 class="font-bold">{{ $channel->name }}</h2><p class="text-xs text-slate-400">{{ $protected ? 'Protected · Read Only' : str($channel->channel_mode)->replace('_',' ')->title() }} · {{ $channel->activeParticipants()->count() }} members {{ $channel->is_archived ? '· Archived' : '' }}</p><p class="mt-1 text-sm text-slate-400">{{ $channel->channel_description }}</p></div>@unless($protected)<div class="flex gap-3"><button wire:click="editChannel({{ $channel->id }})" class="text-xs text-purple-300">Edit</button><button wire:click="archiveChannel({{ $channel->id }})" class="text-xs text-amber-300">{{ $channel->is_archived ? 'Unarchive' : 'Archive' }}</button></div>@endunless</div>
                        @unless($protected)<div class="mt-4 grid gap-2 sm:grid-cols-2">@foreach($channel->participants->filter(fn($p) => $p->user?->hasRole('agent')) as $member)<div class="flex items-center justify-between rounded-lg bg-slate-800 px-3 py-2 text-sm"><span>{{ $member->user->name }} <small class="text-slate-500">{{ $member->channel_blocked_at ? 'Blocked' : ($member->channel_can_post ? 'Publisher' : 'Reader') }}</small></span>@if($member->channel_blocked_at)<button wire:click="unblockMember({{ $channel->id }},{{ $member->user_id }})" class="text-xs text-emerald-300">Unblock</button>@else<button wire:click="blockMember({{ $channel->id }},{{ $member->user_id }})" wire:confirm="Block this Agent from this channel?" class="text-xs text-red-300">Block</button>@endif</div>@endforeach</div>@endunless
                    </article>
                @endforeach
            </div>
            <form wire:submit="saveChannel" class="h-fit rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="text-lg font-bold">{{ $channelId ? 'Edit Channel' : 'Create Channel' }}</h2><div class="mt-4 space-y-3"><input wire:model="channelName" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2" placeholder="Channel name"><textarea wire:model="channelDescription" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2" placeholder="Description (optional)"></textarea><select wire:model.live="channelMode" class="w-full rounded-lg border border-slate-700 bg-slate-950 p-2"><option value="open">Open</option><option value="read_only">Read Only</option><option value="restricted">Restricted Posting</option></select>
                    @if($channelMode === 'restricted')<fieldset class="rounded-xl border border-slate-700 p-3"><legend class="px-2 text-sm font-bold">Allowed Agent Publishers</legend>@foreach($agents as $agent)<label class="mt-2 flex gap-2"><input wire:model="publisherIds" type="checkbox" value="{{ $agent->id }}"> {{ $agent->name }}</label>@endforeach</fieldset>@endif
                    <div class="flex gap-2"><button class="rounded-lg bg-purple-600 px-4 py-2 font-bold">{{ $channelId ? 'Save Changes' : 'Create Channel' }}</button>@if($channelId)<button type="button" wire:click="resetChannelForm" class="rounded-lg bg-slate-700 px-4 py-2">Cancel</button>@endif</div></div>
            </form>
        </section>
    @endif
</div>
