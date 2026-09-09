<?php

namespace App\Livewire\Player;

use App\Models\SpinPromotionalPointLedger;
use App\Models\SpinWheelAssignment;
use App\Models\SpinWheelSetting;
use App\Models\SpinWheelSpin;
use App\Models\User;
use App\Services\Spin\SpinEligibilityService;
use App\Services\Spin\SpinStatisticsService;
use App\Services\Spin\SpinWheelService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SpinWheel extends Component
{
    #[Locked]
    public int $availableSpins = 0;

    #[Locked]
    public string $requestToken = '';

    #[Locked]
    public ?array $result = null;

    #[Locked]
    public int $sajiloPoints = 0;

    #[Locked]
    public int $bonusPoints = 0;

    #[Locked]
    public array $todayWins = [];

    #[Locked]
    public bool $animationPending = false;

    public function mount(SpinEligibilityService $eligibility): void
    {
        abort_unless(Auth::user()?->hasRole('player'), 403);
        $this->requestToken = (string) Str::uuid();
        $this->refreshDisplayState($eligibility);
    }

    public function spin(SpinWheelService $wheel, SpinEligibilityService $eligibility): void
    {
        try {
            $spin = $wheel->spin($this->player(), $this->requestToken);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }
            $this->dispatch('spin-wheel-failed');

            return;
        }
        $this->result = [
            'id' => $spin->id, 'slot' => $spin->wheel_slot, 'name' => $spin->offer_snapshot_name,
            'value' => $spin->offer_snapshot_value, 'type' => $spin->offer_snapshot_type,
            'description' => $spin->offer_snapshot_metadata['description'] ?? null,
            'display_label' => $spin->offer_snapshot_metadata['display_label'] ?? $spin->offer_snapshot_name,
        ];
        $this->animationPending = true;
        $this->requestToken = (string) Str::uuid();
        $settings = SpinWheelSetting::find(1);
        $centerAngle = (($spin->wheel_slot - 1) * 22.5) + 11.25;
        $this->dispatch('spin-wheel-result', spinId: $spin->id, slot: $spin->wheel_slot,
            landingAngle: 360 - $centerAngle, duration: $settings?->animation_duration_ms ?? 4800);
    }

    public function animationFinished(SpinEligibilityService $eligibility): void
    {
        if (! $this->animationPending || ! $this->result) {
            return;
        }
        $this->animationPending = false;
        $this->refreshDisplayState($eligibility);
        $this->dispatch('spin-wheel-reveal-state',
            attempts: $this->availableSpins,
            sajilo: $this->sajiloPoints,
            bonus: $this->bonusPoints,
        );
    }

    public function refreshState(SpinEligibilityService $eligibility): void
    {
        if ($this->animationPending) {
            return;
        }
        $this->refreshDisplayState($eligibility);
        $this->dispatch('spin-wheel-display-state',
            attempts: $this->availableSpins,
            sajilo: $this->sajiloPoints,
            bonus: $this->bonusPoints,
        );
    }

    public function refreshAttempts(SpinEligibilityService $eligibility): void
    {
        $this->refreshState($eligibility);
    }

    public function render()
    {
        $settings = SpinWheelSetting::find(1) ?? new SpinWheelSetting;
        $now = now();
        $assignments = SpinWheelAssignment::with('offer.type')->where('is_active', true)
            ->whereIn('slot_type', ['numeric', 'featured'])
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->whereHas('offer', fn ($q) => $q->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
                ->whereHas('type', fn ($type) => $type->where('is_active', true)))
            ->get()->keyBy(fn ($item) => $item->slot_type.'.'.$item->slot_position);
        $order = [['numeric', 1], ['numeric', 5], ['numeric', 6], ['numeric', 3], ['featured', 1], ['numeric', 2], ['numeric', 4], ['numeric', 9], ['numeric', 10], ['featured', 2], ['numeric', 8], ['featured', 3], ['numeric', 7], ['featured', 4], ['featured', 5], ['featured', 6]];
        $visualSegments = collect($order)->map(function ($slot, $index) use ($assignments) {
            [$type, $position] = $slot;
            $assignment = $assignments->get($type.'.'.$position);

            return [
                'slot' => $index + 1,
                'type' => $type,
                'label' => $type === 'numeric' ? (string) $position : ($assignment?->display_label ?: $assignment?->offer?->name ?: 'FEATURED PRIZE'),
                'assigned' => (bool) $assignment,
            ];
        });

        return view('livewire.player.spin-wheel', [
            'settings' => $settings, 'visualSegments' => $visualSegments,
            'assetUrl' => Storage::url('spinningwheel/sppining wheel.avif'),
        ]);
    }

    private function refreshDisplayState(SpinEligibilityService $eligibility): void
    {
        $this->availableSpins = $eligibility->available($this->player());
        $this->sajiloPoints = (int) SpinPromotionalPointLedger::where('user_id', Auth::id())
            ->where('point_type', 'sajilo_points')->sum('remaining_amount');
        $this->bonusPoints = (int) SpinPromotionalPointLedger::where('user_id', Auth::id())
            ->where('point_type', 'bonus_points')->where('status', 'pending')->sum('remaining_amount');
        $start = now()->startOfDay();
        $end = now()->endOfDay();
        $this->todayWins = SpinWheelSpin::where('user_id', Auth::id())
            ->whereIn('offer_snapshot_type', SpinStatisticsService::WIN_TYPES)
            ->whereBetween('spun_at', [$start, $end])->latest('spun_at')->limit(8)->get()
            ->map(fn (SpinWheelSpin $spin) => [
                'id' => $spin->id,
                'label' => $spin->offer_snapshot_name,
                'type' => $spin->offer_snapshot_type,
            ])->all();
    }

    private function player(): User
    {
        return User::findOrFail(Auth::id());
    }
}
