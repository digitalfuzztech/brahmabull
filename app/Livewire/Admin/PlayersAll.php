<?php

namespace App\Livewire\Admin;


use Livewire\Component;
use App\Models\User;
use Livewire\WithPagination;

class PlayersAll extends Component
{
    use WithPagination;
    public $search = '';
    public $selectedPlayer = null;

    public function getPlayersProperty()
    {
        return User::role('player')
            ->with('playerProfile')
            ->when($this->search, function ($q) {
                $q->where('name', 'like', "%{$this->search}%");
            })
            ->latest()
            ->paginate(30);
    }

    public function openPlayer($id)
    {
        $this->selectedPlayer = User::with([
            'playerProfile',
            'gameAccounts.game',
            'deposits',
            'cashouts'
        ])->findOrFail($id);
    }

    public function closePlayer()
    {
        $this->selectedPlayer = null;
    }
    public function referrer()
    {
        return $this->belongsTo(User::class,'referred_by');
    }
    public function render()
    {
        return view('livewire.admin.players-all')
            ->layout('layouts.private');
    }
}
