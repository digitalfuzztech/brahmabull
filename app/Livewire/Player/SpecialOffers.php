<?php

namespace App\Livewire\Player;

use App\Models\SpecialOffer;
use Livewire\Attributes\On;
use Livewire\Component;

class SpecialOffers extends Component
{
    public bool $listOpen = false;
    public ?int $selectedOfferId = null;

    public function mount(): void
    {
        $this->assertPlayer();
    }

    public function toggleList(): void
    {
        $this->assertPlayer();
        $opening = ! $this->listOpen;
        $this->selectedOfferId = null;
        $this->listOpen = $opening;

        if ($opening) {
            $this->dispatch('bb:offers-opened');
        }
    }

    public function close(): void
    {
        $this->listOpen = false;
        $this->selectedOfferId = null;
    }

    public function showOffer(int $offerId): void
    {
        $this->assertPlayer();
        SpecialOffer::query()->activeOnDate(today())->findOrFail($offerId);
        $this->selectedOfferId = $offerId;
        $this->listOpen = false;
        $this->dispatch('bb:offers-opened');
    }

    #[On('bb:support-opened')]
    public function closeForSupport(): void
    {
        $this->close();
    }

    public function render()
    {
        $this->assertPlayer();
        $offers = SpecialOffer::query()
            ->activeOnDate(today())
            ->orderBy('ends_at')
            ->limit(SpecialOffer::MAX_OFFERS)
            ->get();

        return view('livewire.player.special-offers', [
            'offers' => $offers,
            'selectedOffer' => $this->selectedOfferId
                ? $offers->firstWhere('id', $this->selectedOfferId)
                : null,
        ]);
    }

    private function assertPlayer(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('player'), 403);
    }
}
