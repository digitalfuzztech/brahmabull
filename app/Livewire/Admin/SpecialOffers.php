<?php

namespace App\Livewire\Admin;

use App\Models\SpecialOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class SpecialOffers extends Component
{
    public bool $showForm = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $description = '';
    public string $starts_at = '';
    public string $ends_at = '';
    public bool $is_active = true;

    public function mount(): void
    {
        $this->admin();
    }

    public function create(): void
    {
        $this->admin();

        if (SpecialOffer::query()->count() >= SpecialOffer::MAX_OFFERS) {
            $this->addError('offerLimit', 'Maximum 4 offers reached.');

            return;
        }

        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $offerId): void
    {
        $this->admin();
        $offer = SpecialOffer::query()->findOrFail($offerId);

        $this->editingId = $offer->id;
        $this->name = $offer->name;
        $this->description = $offer->description;
        $this->starts_at = $offer->starts_at->toDateString();
        $this->ends_at = $offer->ends_at->toDateString();
        $this->is_active = $offer->is_active;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->admin();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            if ($this->editingId) {
                SpecialOffer::query()->findOrFail($this->editingId)->update($validated);

                return;
            }

            SpecialOffer::query()->lockForUpdate()->get(['id']);

            if (SpecialOffer::query()->count() >= SpecialOffer::MAX_OFFERS) {
                throw ValidationException::withMessages([
                    'offerLimit' => 'Maximum 4 offers reached.',
                ]);
            }

            SpecialOffer::create($validated);
        });

        session()->flash('success', $this->editingId ? 'Offer updated.' : 'Offer created.');
        $this->closeForm();
    }

    public function toggle(int $offerId): void
    {
        $this->admin();
        $offer = SpecialOffer::query()->findOrFail($offerId);
        $offer->update(['is_active' => ! $offer->is_active]);
    }

    public function delete(int $offerId): void
    {
        $this->admin();
        SpecialOffer::query()->findOrFail($offerId)->delete();
        session()->flash('success', 'Offer deleted.');

        if ($this->editingId === $offerId) {
            $this->closeForm();
        }
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function render()
    {
        $this->admin();

        return view('livewire.admin.special-offers', [
            'offers' => SpecialOffer::query()->orderBy('starts_at')->orderBy('id')->get(),
        ])->layout('layouts.private');
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'starts_at', 'ends_at']);
        $this->is_active = true;
        $this->resetValidation();
    }

    private function admin(): User
    {
        $user = auth()->user();
        abort_unless($user?->hasRole('admin'), 403);

        return $user;
    }
}
