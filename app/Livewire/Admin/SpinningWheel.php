<?php

namespace App\Livewire\Admin;

use App\Models\SpinRewardClaim;
use App\Models\SpinWheelAssignment;
use App\Models\SpinWheelOffer;
use App\Models\SpinWheelOfferType;
use App\Models\SpinWheelSetting;
use App\Models\SpinWheelSpin;
use App\Models\User;
use App\Services\Spin\SpinCategoryProbabilityService;
use App\Services\Spin\SpinOfferService;
use App\Services\Spin\SpinStatisticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SpinningWheel extends Component
{
    use WithPagination;

    #[Url(as: 'tab', history: true)]
    public string $activeTab = 'types';

    public bool $showTypeForm = false;

    public bool $showTypeDeleteConfirm = false;

    public ?int $pendingTypeDeleteId = null;

    public ?int $pendingOfferDeleteId = null;

    public ?int $pendingAssignmentDeleteId = null;

    public string $search = '';

    public string $offerCategoryFilter = '';

    public ?int $typeId = null;

    public string $typeName = '';

    public string $typeSlug = '';

    public string $typeDescription = '';

    public string $typeAction = 'try_again';

    public bool $typeActive = true;

    public ?int $offerId = null;

    public ?int $offerTypeId = null;

    public string $offerName = '';

    public string $offerDescription = '';

    public string $offerDisplayValue = '';

    public string $offerCategory = 'general';

    public int $offerWeight = 1;

    public int $offerScore = 0;

    public bool $offerFeatured = false;

    public bool $offerNotifyStaff = false;

    public bool $offerActive = true;

    public ?string $offerStartsAt = null;

    public ?string $offerEndsAt = null;

    public ?int $offerAmount = null;

    public string $offerCode = '';

    public int $offerValidityDays = 3;

    public ?int $assignmentId = null;

    public string $assignmentSlotType = 'numeric';

    public int $assignmentSlotPosition = 1;

    public ?int $assignmentOfferId = null;

    public string $assignmentLabel = '';

    public int $assignmentWeight = 1;

    public bool $assignmentFeatured = false;

    public bool $assignmentActive = true;

    public ?string $assignmentStartsAt = null;

    public ?string $assignmentEndsAt = null;

    public int $month;

    public int $year;

    public string $winStatus = '';

    public string $winType = '';

    public string $winCategory = '';

    public ?string $winFrom = null;

    public ?string $winTo = null;

    public string $winsMode = 'all';

    public array $settingForm = [];

    public string $featuredLabels = '';

    public function mount(): void
    {
        $this->admin();
        $this->activeTab = in_array(request('tab'), ['types', 'offers', 'assignments', 'wins', 'settings'], true) ? request('tab') : 'types';
        $this->month = (int) now()->month;
        $this->year = (int) now()->year;
        $this->settingForm = SpinWheelSetting::findOrFail(1)->only([
            'is_enabled', 'launcher_enabled', 'maximum_stored_attempts', 'animation_duration_ms',
            'celebration_enabled', 'show_recent_win', 'terms_text',
            'try_again_chance', 'free_spin_chance', 'bonus_points_chance', 'sajilo_points_chance', 'badge_chance',
        ]);
        $this->featuredLabels = implode("\n", SpinWheelSetting::findOrFail(1)->featured_labels ?? []);
    }

    public function saveType(SpinOfferService $service): void
    {
        $data = $this->validate(['typeName' => 'required|string|max:100',
            'typeAction' => 'required|in:'.implode(',', SpinOfferService::ACTIONS), 'typeActive' => 'boolean']);
        $service->saveType($this->admin(), $this->typeId ? SpinWheelOfferType::findOrFail($this->typeId) : null, [
            'name' => $data['typeName'],
            'action_type' => $data['typeAction'], 'is_active' => $data['typeActive'],
        ]);
        session()->flash('success', $this->typeId ? 'Offer Type updated.' : 'Offer Type created.');
        $this->resetTypeForm();
        $this->showTypeForm = false;
    }

    public function setTab(string $tab): void
    {
        abort_unless(in_array($tab, ['types', 'offers', 'assignments', 'wins', 'settings'], true), 404);
        $this->activeTab = $tab;
        $this->resetValidation();
    }

    public function openTypeForm(): void
    {
        $this->resetTypeForm();
        $this->showTypeForm = true;
    }

    public function closeTypeForm(): void
    {
        $this->resetTypeForm();
        $this->showTypeForm = false;
    }

    public function editType(int $id): void
    {
        $type = SpinWheelOfferType::findOrFail($id);
        $this->typeId = $id;
        $this->typeName = $type->name;
        $this->typeSlug = $type->slug;
        $this->typeDescription = $type->description ?? '';
        $this->typeAction = $type->action_type;
        $this->typeActive = $type->is_active;
        $this->showTypeForm = true;
    }

    public function confirmDeleteType(int $id): void
    {
        SpinWheelOfferType::findOrFail($id);
        $this->pendingTypeDeleteId = $id;
        $this->showTypeDeleteConfirm = true;
    }

    public function cancelDeleteType(): void
    {
        $this->pendingTypeDeleteId = null;
        $this->showTypeDeleteConfirm = false;
    }

    public function deleteConfirmedType(SpinOfferService $service): void
    {
        $type = SpinWheelOfferType::findOrFail($this->pendingTypeDeleteId);
        $archived = $type->offers()->exists();
        $service->deleteType($this->admin(), $type);
        session()->flash('success', $archived ? 'Offer Type is in use and was safely disabled.' : 'Offer Type deleted.');
        $this->cancelDeleteType();
        $this->resetTypeForm();
    }

    public function saveOffer(SpinOfferService $service): void
    {
        $data = $this->validate(['offerTypeId' => 'required|exists:spin_wheel_offer_types,id', 'offerName' => 'required|string|max:150',
            'offerDisplayValue' => 'nullable|string|max:100', 'offerFeatured' => 'boolean', 'offerActive' => 'boolean',
            'offerStartsAt' => 'nullable|date', 'offerEndsAt' => 'nullable|date|after:offerStartsAt',
            'offerValidityDays' => 'integer|min:1|max:365', 'offerWeight' => 'integer|min:1|max:10000',
            'offerNotifyStaff' => 'boolean']);
        $type = SpinWheelOfferType::findOrFail($data['offerTypeId']);
        $editing = (bool) $this->offerId;
        $service->saveOffer($this->admin(), $this->offerId ? SpinWheelOffer::findOrFail($this->offerId) : null, [
            'offer_type_id' => $data['offerTypeId'], 'name' => $data['offerName'], 'description' => null,
            'display_value' => $data['offerDisplayValue'] !== '' ? $data['offerDisplayValue'] : null,
            'category' => $type->action_type,
            'rarity_weight' => $data['offerWeight'], 'ranking_score' => is_numeric($data['offerDisplayValue']) ? (int) $data['offerDisplayValue'] : 0,
            'is_featured' => $data['offerFeatured'], 'notify_staff' => $data['offerNotifyStaff'], 'is_active' => $data['offerActive'],
            'starts_at' => $data['offerStartsAt'] ?: null, 'ends_at' => $data['offerEndsAt'] ?: null,
            'metadata' => $type->action_type === 'badge' ? ['valid_days' => $data['offerValidityDays']] : [],
        ]);
        $this->resetOfferForm();
        session()->flash('success', $editing ? 'Offer updated.' : 'Offer created.');
    }

    public function editOffer(int $id): void
    {
        $offer = SpinWheelOffer::findOrFail($id);
        $this->offerId = $id;
        $this->offerTypeId = $offer->offer_type_id;
        $this->offerName = $offer->name;
        $this->offerDescription = $offer->description ?? '';
        $this->offerDisplayValue = $offer->display_value ?? '';
        $this->offerCategory = $offer->category;
        $this->offerWeight = $offer->rarity_weight;
        $this->offerScore = $offer->ranking_score;
        $this->offerFeatured = $offer->is_featured;
        $this->offerNotifyStaff = $offer->notify_staff;
        $this->offerActive = $offer->is_active;
        $this->offerStartsAt = $offer->starts_at?->format('Y-m-d\TH:i');
        $this->offerEndsAt = $offer->ends_at?->format('Y-m-d\TH:i');
        $this->offerValidityDays = $offer->metadata['valid_days'] ?? 3;
    }

    public function confirmDeleteOffer(int $id): void
    {
        SpinWheelOffer::findOrFail($id);
        $this->pendingOfferDeleteId = $id;
    }

    public function cancelDeleteOffer(): void
    {
        $this->pendingOfferDeleteId = null;
    }

    public function deleteConfirmedOffer(SpinOfferService $service): void
    {
        $service->deleteOffer($this->admin(), SpinWheelOffer::findOrFail($this->pendingOfferDeleteId));
        $this->cancelDeleteOffer();
        session()->flash('success', 'Offer removed or safely disabled.');
    }

    public function saveAssignment(SpinOfferService $service): void
    {
        $data = $this->validate(['assignmentSlotType' => 'required|in:numeric,featured', 'assignmentSlotPosition' => 'required|integer|min:1|max:10',
            'assignmentOfferId' => 'required|exists:spin_wheel_offers,id', 'assignmentLabel' => 'nullable|string|max:100',
            'assignmentWeight' => 'integer|min:1|max:100000', 'assignmentActive' => 'boolean']);
        $editing = (bool) $this->assignmentId;
        $service->saveAssignment($this->admin(), $this->assignmentId ? SpinWheelAssignment::findOrFail($this->assignmentId) : null, [
            'slot_type' => $data['assignmentSlotType'], 'slot_position' => $data['assignmentSlotPosition'],
            'offer_id' => $data['assignmentOfferId'], 'display_label' => $data['assignmentLabel'] ?: null,
            'weight' => $data['assignmentWeight'], 'is_active' => $data['assignmentActive'],
            'starts_at' => null, 'ends_at' => null,
        ]);
        $this->resetAssignmentForm();
        session()->flash('success', $editing ? 'Wheel assignment updated.' : 'Wheel assignment created.');
    }

    public function editAssignment(int $id): void
    {
        $item = SpinWheelAssignment::findOrFail($id);
        $this->assignmentId = $id;
        $this->assignmentSlotType = $item->slot_type;
        $this->assignmentSlotPosition = $item->slot_position;
        $this->assignmentOfferId = $item->offer_id;
        $this->assignmentLabel = $item->display_label ?? '';
        $this->assignmentWeight = $item->weight;
        $this->assignmentActive = $item->is_active;
    }

    public function confirmDeleteAssignment(int $id): void
    {
        SpinWheelAssignment::findOrFail($id);
        $this->pendingAssignmentDeleteId = $id;
    }

    public function cancelDeleteAssignment(): void
    {
        $this->pendingAssignmentDeleteId = null;
    }

    public function deleteConfirmedAssignment(SpinOfferService $service): void
    {
        $service->deleteAssignment($this->admin(), SpinWheelAssignment::findOrFail($this->pendingAssignmentDeleteId));
        $this->cancelDeleteAssignment();
        $this->resetAssignmentForm();
        session()->flash('success', 'Wheel assignment removed.');
    }

    public function setWinsMode(string $mode): void
    {
        abort_unless(in_array($mode, ['top_wins', 'top_winners', 'monthly', 'all'], true), 404);
        $this->winsMode = $mode;
    }

    public function saveSettings(SpinCategoryProbabilityService $probabilities): void
    {
        $this->admin();
        $settings = SpinWheelSetting::findOrFail(1);
        $data = $this->validate(['settingForm.is_enabled' => 'boolean', 'settingForm.launcher_enabled' => 'boolean',
            'settingForm.maximum_stored_attempts' => 'integer|min:1|max:3', 'settingForm.animation_duration_ms' => 'integer|min:1500|max:15000',
            'settingForm.celebration_enabled' => 'boolean', 'settingForm.show_recent_win' => 'boolean', 'settingForm.terms_text' => 'nullable|string|max:5000',
            'settingForm.try_again_chance' => 'required|integer|min:0|max:100',
            'settingForm.free_spin_chance' => 'required|integer|min:0|max:100',
            'settingForm.bonus_points_chance' => 'required|integer|min:0|max:100',
            'settingForm.sajilo_points_chance' => 'required|integer|min:0|max:100',
            'settingForm.badge_chance' => 'required|integer|min:0|max:100']);
        $weights = collect(SpinCategoryProbabilityService::DEFAULTS)->mapWithKeys(fn ($weight, $category) => [
            $category => (int) $data['settingForm'][$category.'_chance'],
        ])->all();
        $probabilities->validate($weights);
        $warnings = app(SpinOfferService::class)->categoryWarningsForWeights($weights);
        if ($warnings !== []) {
            throw ValidationException::withMessages(['settingForm.reward_chances' => $warnings]);
        }
        $settings->update($data['settingForm'] + ['featured_labels' => collect(preg_split('/\r\n|\r|\n/', $this->featuredLabels))->map(fn ($label) => trim($label))->filter()->take(20)->values()->all()]);
        session()->flash('success', 'Spin Wheel settings saved.');
    }

    public function updateClaim(int $id, string $status): void
    {
        $this->admin();
        abort_unless(in_array($status, ['approved', 'fulfilled', 'rejected'], true), 422);
        SpinRewardClaim::findOrFail($id)->update(['status' => $status, 'processed_by' => Auth::id(), 'processed_at' => now()]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage('winsPage');
        $this->resetPage('topWinsPage');
        $this->resetPage('topWinnersPage');
        $this->resetPage('monthlyWinnersPage');
    }

    public function updatedWinStatus(): void
    {
        $this->resetPage('winsPage');
    }

    public function updatedWinType(): void
    {
        $this->resetPage('winsPage');
    }

    public function updatedWinCategory(): void
    {
        $this->resetPage('winsPage');
    }

    public function updatedWinFrom(): void
    {
        $this->resetPage('winsPage');
    }

    public function updatedWinTo(): void
    {
        $this->resetPage('winsPage');
    }

    public function updatedMonth(): void
    {
        $this->resetPage('monthlyWinnersPage');
    }

    public function updatedYear(): void
    {
        $this->resetPage('monthlyWinnersPage');
    }

    public function resetTypeForm(): void
    {
        $this->reset(['typeId', 'typeName', 'typeSlug', 'typeDescription']);
        $this->typeAction = 'try_again';
        $this->typeActive = true;
    }

    public function resetOfferForm(): void
    {
        $this->reset(['offerId', 'offerTypeId', 'offerName', 'offerDescription', 'offerDisplayValue', 'offerStartsAt', 'offerEndsAt', 'offerAmount', 'offerCode']);
        $this->offerCategory = 'general';
        $this->offerWeight = 1;
        $this->offerScore = 0;
        $this->offerFeatured = false;
        $this->offerNotifyStaff = false;
        $this->offerActive = true;
        $this->offerValidityDays = 3;
    }

    public function resetAssignmentForm(): void
    {
        $this->reset(['assignmentId', 'assignmentOfferId', 'assignmentLabel', 'assignmentStartsAt', 'assignmentEndsAt']);
        $this->assignmentSlotType = 'numeric';
        $this->assignmentSlotPosition = 1;
        $this->assignmentWeight = 1;
        $this->assignmentActive = true;
    }

    public function render(SpinStatisticsService $statistics)
    {
        $this->admin();
        $settings = SpinWheelSetting::findOrFail(1);
        $offers = SpinWheelOffer::with(['type', 'assignments'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->offerCategoryFilter, fn ($q) => $q->where('category', $this->offerCategoryFilter))
            ->latest()->get();
        $wins = SpinWheelSpin::with('user')
            ->whereIn('offer_snapshot_type', SpinStatisticsService::WIN_TYPES)
            ->when($this->search, fn ($q) => $q->where(function ($filtered) {
            $filtered->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$this->search.'%'))
                ->orWhere('offer_snapshot_name', 'like', '%'.$this->search.'%');
        }))
            ->when($this->winStatus, fn ($q) => $q->where('status', $this->winStatus))
            ->when($this->winType, fn ($q) => $q->where('offer_snapshot_type', $this->winType))
            ->when($this->winCategory, fn ($q) => $q->where('offer_snapshot_category', $this->winCategory))
            ->when($this->winFrom, fn ($q) => $q->whereDate('spun_at', '>=', $this->winFrom))
            ->when($this->winTo, fn ($q) => $q->whereDate('spun_at', '<=', $this->winTo))
            ->latest('spun_at')->paginate(20, ['*'], 'winsPage');

        $categoryWarnings = app(SpinOfferService::class)->categoryWarnings($settings);

        return view('livewire.admin.spinning-wheel', [
            'types' => SpinWheelOfferType::orderBy('name')->get(), 'offers' => $offers,
            'assignments' => SpinWheelAssignment::with('offer.type')->whereIn('slot_type', ['numeric', 'featured'])->orderBy('slot_type')->orderBy('slot_position')->get(), 'wins' => $wins,
            'topWins' => $statistics->topWins()->paginate(20, ['*'], 'topWinsPage'),
            'topWinners' => $statistics->winners()->with('user')->paginate(20, ['*'], 'topWinnersPage'),
            'monthlyWinners' => $statistics->winners($this->month, $this->year)->with('user')->paginate(20, ['*'], 'monthlyWinnersPage'),
            'claims' => SpinRewardClaim::with(['user', 'offer', 'spin'])->latest()->limit(50)->get(), 'settings' => $settings,
            'actions' => SpinOfferService::ACTIONS, 'actionLabels' => SpinOfferService::ACTION_LABELS, 'categories' => SpinOfferService::CATEGORIES,
            'categoryWarnings' => $categoryWarnings,
        ])->layout('layouts.private');
    }

    private function admin(): User
    {
        $user = User::findOrFail(Auth::id());
        app(SpinOfferService::class)->assertAdmin($user);

        return $user;
    }
}
