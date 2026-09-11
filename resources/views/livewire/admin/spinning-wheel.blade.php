<div class="space-y-6 p-4 text-slate-100 sm:p-6">
    <header class="rounded-3xl border border-amber-400/20 bg-gradient-to-r from-slate-950 via-purple-950 to-slate-950 p-6 shadow-2xl">
        <p class="text-xs font-black uppercase tracking-[.35em] text-amber-300">BrahmaBull Rewards</p>
        <h1 class="mt-2 text-3xl font-black">Spinning Wheel</h1>
        <p class="mt-2 text-sm text-slate-400">Configure safe promotional rewards and the sixteen visible wheel slots.</p>
    </header>
    @if(session('success'))<div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-emerald-200">{{ session('success') }}</div>@endif
    <nav class="flex flex-wrap gap-2 rounded-2xl border border-slate-800 bg-slate-900/80 p-2">
        @foreach(['types'=>'1. Offer Types','offers'=>'2. Offers','assignments'=>'3. Wheel Assignment','wins'=>'4. Wins','settings'=>'5. Settings'] as $key=>$label)
            <button type="button" data-spin-tab="{{ $key }}" wire:key="spin-tab-{{ $key }}" wire:click="setTab('{{ $key }}')" class="rounded-xl px-4 py-2 text-sm font-bold {{ $activeTab===$key?'bg-amber-400 text-slate-950':'text-slate-300 hover:bg-slate-800' }}">{{ $label }}</button>
        @endforeach
    </nav>

    @if($activeTab === 'types')
        <div class="space-y-4">
            <button type="button" data-spin-add-type wire:click="openTypeForm" class="rounded-xl bg-purple-600 px-5 py-2 font-bold">Add Offer Type</button>
            @if($showTypeForm)
            <div data-spin-type-modal class="bb-admin-modal-overlay fixed inset-0 z-[200] flex items-center justify-center" wire:key="offer-type-form-modal">
            <form wire:submit="saveType" class="bb-admin-modal-shell bb-admin-modal-self-scroll max-w-md space-y-4 p-5">
                <div><h2 class="text-xl font-black">{{ $typeId?'Edit':'Add' }} Offer Type</h2><p class="text-xs text-slate-400">Maximum 10 active types.</p></div>
                <label class="block text-sm font-bold">Offer Name<input data-spin-type-name wire:model.live.debounce.250ms="typeName" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950" placeholder="Loyalty Reward"></label>
                <label class="block text-sm font-bold">Offer Slug<input value="{{ Str::slug($typeName) }}" disabled class="mt-1 w-full rounded-xl border-slate-800 bg-slate-950/60 text-slate-400" placeholder="generated-automatically"></label>
                <label class="block text-sm font-bold">Category<select data-spin-type-category wire:model="typeAction" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950">@foreach($actionLabels as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label class="flex justify-between rounded-xl bg-slate-950 p-3 text-sm font-bold">Active<input type="checkbox" wire:model="typeActive"></label>
                @error('typeName')<p class="text-sm text-red-300">{{ $message }}</p>@enderror @error('typeActive')<p class="text-sm text-red-300">{{ $message }}</p>@enderror
                <div class="flex gap-2"><button type="submit" data-spin-save-type class="bb-admin-modal-primary px-5 py-2">Save Type</button><button type="button" wire:click="closeTypeForm" class="bb-admin-modal-secondary px-4 py-2">Cancel</button></div>
            </form>
            </div>
            @endif
            <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900">
                <div class="border-b border-slate-800 p-4 text-sm text-slate-400">{{ $types->where('is_active',true)->count() }} of 10 active</div>
                <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-950 text-slate-400"><tr><th class="p-3">Offer Name</th><th>Slug</th><th>Category</th><th>Active</th><th>Actions</th></tr></thead><tbody>
                @forelse($types as $type)<tr data-spin-type-row="{{ $type->id }}" class="border-t border-slate-800" wire:key="offer-type-{{ $type->id }}"><td class="p-3 font-bold">{{ $type->name }}</td><td class="text-slate-400">{{ $type->slug }}</td><td>{{ $actionLabels[$type->action_type]??Str::headline($type->action_type) }}</td><td>{{ $type->is_active?'Yes':'No' }}</td><td class="whitespace-nowrap"><button type="button" data-spin-edit-type="{{ $type->id }}" wire:click="editType({{ $type->id }})" class="mr-3 text-purple-300">Edit</button><button type="button" data-spin-delete-type="{{ $type->id }}" wire:click="confirmDeleteType({{ $type->id }})" class="text-red-300">Delete</button></td></tr>
                @empty<tr><td colspan="5" class="p-8 text-center text-slate-500">Create the first Offer Type.</td></tr>@endforelse
                </tbody></table></div>
            </div>
        </div>
    @elseif($activeTab === 'offers')
        <div class="grid gap-6 xl:grid-cols-[420px_1fr]">
            <form wire:submit="saveOffer" class="space-y-4 rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="text-xl font-black">{{ $offerId?'Edit':'Add' }} Offer</h2>
                <label class="block text-sm font-bold">Offer Name<input wire:model="offerName" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950" placeholder="10 Sajilo Points"></label>
                <label class="block text-sm font-bold">Offer Type<select wire:model="offerTypeId" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950"><option value="">Choose type</option>@foreach($types->where('is_active',true) as $type)<option value="{{ $type->id }}">{{ $type->name }} - {{ $actionLabels[$type->action_type]??Str::headline($type->action_type) }}</option>@endforeach</select></label>
                <label class="block text-sm font-bold">Offer Value<input wire:model="offerDisplayValue" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950" placeholder="25, 1-3 for Free Spin, blank for Badge"><span class="mt-1 block text-xs font-normal text-slate-500">Try Again and Badge may be blank. Free Spin accepts 1, 2, or 3.</span></label>
                <label class="block text-sm font-bold">Offer Weight<input type="number" min="1" max="10000" wire:model="offerWeight" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950"><span class="mt-1 block text-xs font-normal text-slate-500">Relative chance after this reward category is selected.</span></label>
                <label class="block text-sm font-bold">Badge Validity (days)<input type="number" min="1" max="365" wire:model="offerValidityDays" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950"><span class="mt-1 block text-xs font-normal text-slate-500">Used only by Badge rewards; defaults to 3 days.</span></label>
                <div class="grid grid-cols-2 gap-3"><label class="text-sm font-bold">Starts<input type="datetime-local" wire:model="offerStartsAt" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950"></label><label class="text-sm font-bold">Ends<input type="datetime-local" wire:model="offerEndsAt" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950"></label></div>
                <div class="grid gap-3 sm:grid-cols-3"><label class="flex justify-between rounded-xl bg-slate-950 p-3 text-sm font-bold">Featured<input type="checkbox" wire:model="offerFeatured"></label><label class="flex justify-between rounded-xl bg-slate-950 p-3 text-sm font-bold">Notify staff<input type="checkbox" wire:model="offerNotifyStaff"></label><label class="flex justify-between rounded-xl bg-slate-950 p-3 text-sm font-bold">Active<input type="checkbox" wire:model="offerActive"></label></div>
                @foreach(['offerName','offerTypeId','offerDisplayValue','offerStartsAt','offerEndsAt'] as $field)@error($field)<p class="text-sm text-red-300">{{ $message }}</p>@enderror @endforeach
                <div class="flex gap-2"><button class="rounded-xl bg-purple-600 px-5 py-2 font-bold">Save Offer</button>@if($offerId)<button type="button" wire:click="resetOfferForm" class="rounded-xl bg-slate-700 px-4 py-2">Cancel</button>@endif</div>
            </form>
            <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900"><table class="w-full text-left text-sm"><thead class="bg-slate-950 text-slate-400"><tr><th class="p-3">Offer</th><th>Type</th><th>Value</th><th>Featured</th><th>Active</th><th>Schedule</th><th>Actions</th></tr></thead><tbody>
            @forelse($offers as $offer)<tr class="border-t border-slate-800"><td class="p-3 font-bold">{{ $offer->name }}</td><td>{{ $offer->type?->name }}</td><td>{{ $offer->display_value?:'None' }}</td><td>{{ $offer->is_featured?'Yes':'No' }}</td><td>{{ $offer->is_active?'Yes':'No' }}</td><td class="text-xs text-slate-400">{{ $offer->starts_at?->format('M j, Y')?:'Now' }} - {{ $offer->ends_at?->format('M j, Y')?:'Open' }}</td><td class="whitespace-nowrap"><button type="button" wire:click="editOffer({{ $offer->id }})" class="mr-3 text-purple-300">Edit</button><button type="button" wire:click="confirmDeleteOffer({{ $offer->id }})" class="text-red-300">Delete</button></td></tr>
            @empty<tr><td colspan="7" class="p-8 text-center text-slate-500">Create an offer after adding an Offer Type.</td></tr>@endforelse
            </tbody></table></div>
        </div>
    @elseif($activeTab === 'assignments')
        <form wire:submit="saveAssignment" class="grid gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-5 md:grid-cols-2 xl:grid-cols-6">
            <label class="text-sm font-bold">Slot Group<select wire:model.live="assignmentSlotType" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950"><option value="numeric">Numeric (10)</option><option value="featured">Featured text (6)</option></select></label>
            <label class="text-sm font-bold">Slot Position<input type="number" min="1" max="{{ $assignmentSlotType==='featured'?6:10 }}" wire:model="assignmentSlotPosition" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950"></label>
            <label class="text-sm font-bold xl:col-span-2">Assigned Offer<select wire:model="assignmentOfferId" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950"><option value="">Choose active offer</option>@foreach($offers->where('is_active',true) as $offer)<option value="{{ $offer->id }}">{{ $offer->name }}</option>@endforeach</select></label>
            <label class="text-sm font-bold">Display Text<input wire:model="assignmentLabel" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950" placeholder="Featured slots"></label>
            <label class="flex items-center gap-2 text-sm font-bold"><input type="checkbox" wire:model="assignmentActive"> Active</label>
            <div class="flex gap-2 xl:col-span-5"><button class="rounded-xl bg-purple-600 px-5 py-2 font-bold">Save Assignment</button>@if($assignmentId)<button type="button" wire:click="resetAssignmentForm" class="rounded-xl bg-slate-700 px-4 py-2">Cancel</button>@endif</div>
            @error('assignmentSlotPosition')<p class="text-sm text-red-300 xl:col-span-6">{{ $message }}</p>@enderror
        </form>
        @php($slotMap=$assignments->keyBy(fn($item)=>$item->slot_type.'.'.$item->slot_position))
        <div class="grid gap-6 lg:grid-cols-2">
            @foreach(['numeric'=>10,'featured'=>6] as $group=>$count)
                <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5"><h2 class="text-lg font-black">{{ $group==='numeric'?'10 Numeric Slots':'6 Featured Reward Slots' }}</h2><div class="mt-4 grid gap-3 sm:grid-cols-2">
                @for($position=1;$position<=$count;$position++) @php($item=$slotMap->get($group.'.'.$position))
                    <div class="rounded-xl border p-3 {{ $item?'border-purple-500/50 bg-purple-500/10':'border-slate-800 bg-slate-950/60' }}"><div class="flex items-center justify-between"><strong>{{ $group==='numeric'?'Number '.$position:'Featured '.$position }}</strong><span class="text-xs {{ $item?->is_active?'text-emerald-300':'text-slate-500' }}">{{ $item ? ($item->is_active?'Active':'Disabled') : 'Unassigned' }}</span></div><p class="mt-2 truncate text-sm text-slate-300">{{ $item?->display_label?:$item?->offer?->name?:'No offer assigned' }}</p>@if($item)<div class="mt-3 flex gap-3"><button type="button" wire:click="editAssignment({{ $item->id }})" class="text-xs text-purple-300">Edit</button><button type="button" wire:click="confirmDeleteAssignment({{ $item->id }})" class="text-xs text-red-300">Remove</button></div>@endif</div>
                @endfor
                </div></section>
            @endforeach
        </div>
    @elseif($activeTab === 'wins')
        <div class="flex flex-wrap gap-2">@foreach(['top_wins'=>'Top Wins','top_winners'=>'Top Winners','monthly'=>'Monthly Winners','all'=>'All Wins'] as $mode=>$label)<button type="button" wire:click="setWinsMode('{{ $mode }}')" class="rounded-full px-3 py-1 text-sm {{ $winsMode===$mode?'bg-amber-400 text-slate-950':'bg-slate-800' }}">{{ $label }}</button>@endforeach</div>
        @if($winsMode === 'top_wins')
            <div class="overflow-x-auto rounded-2xl border border-slate-800"><table class="w-full text-left text-sm"><thead class="bg-slate-900 text-slate-400"><tr><th class="p-3">Player</th><th>Reward</th><th>Category</th><th>Value</th><th>Slot</th><th>Date</th><th>Status</th></tr></thead><tbody>@foreach($topWins as $spin)<tr class="border-t border-slate-800"><td class="p-3">{{ $spin->user?->name }}</td><td>{{ $spin->offer_snapshot_name }}</td><td>{{ $actionLabels[$spin->offer_snapshot_type]??Str::headline($spin->offer_snapshot_type) }}</td><td>{{ $spin->offer_snapshot_value?:'None' }}</td><td>{{ $spin->wheel_slot?:$spin->wheel_number }}</td><td>{{ $spin->spun_at?->format('Y-m-d H:i') }}</td><td>{{ $spin->status }}</td></tr>@endforeach</tbody></table></div>{{ $topWins->links() }}
        @elseif($winsMode === 'top_winners' || $winsMode === 'monthly')
            @if($winsMode === 'monthly')<div class="flex flex-wrap gap-2 rounded-xl bg-slate-900 p-3"><label>Month <input type="number" min="1" max="12" wire:model.live="month" class="w-20 rounded bg-slate-950"></label><label>Year <input type="number" min="2000" max="2100" wire:model.live="year" class="w-24 rounded bg-slate-950"></label></div>@endif
            @php($winnerRows=$winsMode==='monthly'?$monthlyWinners:$topWinners)
            <div class="overflow-x-auto rounded-2xl border border-slate-800"><table class="w-full text-left text-sm"><thead class="bg-slate-900 text-slate-400"><tr><th class="p-3">Player</th>@if($winsMode==='monthly')<th>Month</th>@endif<th>Spins</th><th>Wins</th><th>Reward Score</th><th>Best Win</th>@if($winsMode!=='monthly')<th>Last Win</th>@endif</tr></thead><tbody>@foreach($winnerRows as $winner)<tr class="border-t border-slate-800"><td class="p-3">{{ $winner->user?->name }}</td>@if($winsMode==='monthly')<td>{{ sprintf('%02d/%d',$month,$year) }}</td>@endif<td>{{ $winner->total_spins }}</td><td>{{ $winner->total_wins }}</td><td>{{ $winner->reward_score }}</td><td>{{ $winner->best_score }}</td>@if($winsMode!=='monthly')<td>{{ $winner->last_win }}</td>@endif</tr>@endforeach</tbody></table></div>{{ $winnerRows->links() }}
        @else
            <div class="flex flex-wrap gap-2 rounded-xl bg-slate-900 p-3"><select wire:model.live="winStatus" class="rounded-lg border-slate-700 bg-slate-950"><option value="">All statuses</option><option value="awarded">Awarded</option></select><select wire:model.live="winType" class="rounded-lg border-slate-700 bg-slate-950"><option value="">All categories</option>@foreach($actionLabels as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><input type="date" wire:model.live="winFrom" class="rounded-lg border-slate-700 bg-slate-950"><input type="date" wire:model.live="winTo" class="rounded-lg border-slate-700 bg-slate-950"></div>
            <div class="overflow-x-auto rounded-2xl border border-slate-800"><table class="w-full text-left text-sm"><thead class="bg-slate-900 text-slate-400"><tr><th class="p-3">Player</th><th>Reward</th><th>Category</th><th>Value</th><th>Slot</th><th>Date</th><th>Status</th></tr></thead><tbody>@foreach($wins as $spin)<tr class="border-t border-slate-800"><td class="p-3">{{ $spin->user?->name }}</td><td>{{ $spin->offer_snapshot_name }}</td><td>{{ $actionLabels[$spin->offer_snapshot_type]??Str::headline($spin->offer_snapshot_type) }}</td><td>{{ $spin->offer_snapshot_value?:'None' }}</td><td>{{ $spin->wheel_slot?:$spin->wheel_number }}</td><td>{{ $spin->spun_at?->format('Y-m-d H:i') }}</td><td>{{ $spin->status }}</td></tr>@endforeach</tbody></table></div>{{ $wins->links() }}
        @endif
        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4"><h2 class="font-black">Reward Claims</h2>@forelse($claims as $claim)<div class="mt-2 flex flex-wrap items-center justify-between gap-2 border-t border-slate-800 pt-2"><span>{{ $claim->user?->name }} - {{ $claim->offer?->name??$claim->spin?->offer_snapshot_name }} - {{ ucfirst($claim->status) }}</span><div>@foreach(['approved','fulfilled','rejected'] as $status)<button wire:click="updateClaim({{ $claim->id }},'{{ $status }}')" class="ml-1 rounded bg-slate-700 px-2 py-1 text-xs">{{ ucfirst($status) }}</button>@endforeach</div></div>@empty<p class="mt-3 text-sm text-slate-500">No pending reward claims.</p>@endforelse</div>
    @else
        <form wire:submit="saveSettings" class="max-w-2xl space-y-4 rounded-2xl border border-slate-800 bg-slate-900 p-6"><h2 class="text-xl font-black">Spin Wheel Settings</h2>
            @foreach($categoryWarnings as $warning)<p class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-3 text-sm text-amber-200">{{ $warning }}</p>@endforeach
            <label class="flex justify-between">Spin Wheel Enabled <input type="checkbox" wire:model="settingForm.is_enabled"></label><label class="flex justify-between">Launcher Enabled <input type="checkbox" wire:model="settingForm.launcher_enabled"></label>
            <label class="block">Maximum Stored Attempts<input type="number" min="1" max="3" wire:model="settingForm.maximum_stored_attempts" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950"></label><label class="block">Animation Duration (milliseconds)<input type="number" wire:model="settingForm.animation_duration_ms" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950"></label>
            <label class="flex justify-between">Celebration Enabled <input type="checkbox" wire:model="settingForm.celebration_enabled"></label><label class="flex justify-between">Show Recent Win <input type="checkbox" wire:model="settingForm.show_recent_win"></label><label class="block">Terms Text<textarea wire:model="settingForm.terms_text" class="mt-1 w-full rounded-xl border-slate-700 bg-slate-950"></textarea></label>
            <fieldset class="rounded-2xl border border-amber-300/20 bg-slate-950/60 p-4"><legend class="px-2 font-black text-amber-300">Reward Chances</legend><p class="mb-3 text-xs text-slate-400">Category chances are selected first. Offer weights apply inside the winning category.</p>
                @foreach($actionLabels as $value=>$label)<label class="mb-2 flex items-center justify-between gap-4 text-sm"><span>{{ $label }}</span><span class="flex items-center gap-1"><input type="number" min="0" max="100" wire:model="settingForm.{{ $value }}_chance" class="w-24 rounded-lg border-slate-700 bg-slate-900 text-right"><span>%</span></span></label>@endforeach
                @error('settingForm.reward_chances')<p class="mt-2 text-sm text-red-300">{{ $message }}</p>@enderror
            </fieldset>
            <button class="rounded-xl bg-purple-600 px-5 py-2 font-bold">Save Settings</button>
        </form>
    @endif

    @if($showTypeDeleteConfirm)
        <div data-spin-delete-modal class="bb-admin-modal-overlay fixed inset-0 z-[210] flex items-center justify-center" wire:key="offer-type-delete-modal">
            <section class="bb-admin-modal-shell max-w-md p-6 text-center">
                <h2 class="text-xl font-black">Do you really want to delete this offer type?</h2>
                <p class="mt-2 text-sm text-slate-400">Types already used by offers will be safely disabled.</p>
                <div class="mt-6 flex justify-center gap-3"><button type="button" data-spin-delete-yes wire:click="deleteConfirmedType" class="rounded-xl bg-red-600 px-5 py-2 font-bold">Yes</button><button type="button" data-spin-delete-no wire:click="cancelDeleteType" class="bb-admin-modal-secondary px-5 py-2">No</button></div>
            </section>
        </div>
    @endif
    @if($pendingOfferDeleteId)
        <div class="bb-admin-modal-overlay fixed inset-0 z-[210] flex items-center justify-center" wire:key="offer-delete-modal">
            <div class="bb-admin-modal-shell max-w-md p-6 text-center">
                <h3 class="text-xl font-black">Delete this offer?</h3><p class="mt-3 text-slate-300">Used offers will be safely disabled to preserve history.</p>
                <div class="mt-6 flex justify-center gap-3"><button type="button" wire:click="deleteConfirmedOffer" class="rounded-xl bg-red-600 px-5 py-2 font-bold">Yes</button><button type="button" wire:click="cancelDeleteOffer" class="bb-admin-modal-secondary px-5 py-2">No</button></div>
            </div>
        </div>
    @endif
    @if($pendingAssignmentDeleteId)
        <div class="bb-admin-modal-overlay fixed inset-0 z-[210] flex items-center justify-center" wire:key="assignment-delete-modal">
            <div class="bb-admin-modal-shell max-w-md p-6 text-center">
                <h3 class="text-xl font-black">Remove this wheel assignment?</h3>
                <div class="mt-6 flex justify-center gap-3"><button type="button" wire:click="deleteConfirmedAssignment" class="rounded-xl bg-red-600 px-5 py-2 font-bold">Yes</button><button type="button" wire:click="cancelDeleteAssignment" class="bb-admin-modal-secondary px-5 py-2">No</button></div>
            </div>
        </div>
    @endif
</div>
