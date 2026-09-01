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
            ->with(['playerProfile', 'referrer'])
            ->withCount([
                'deposits as normal_deposits_count',
                'brahmaDeposits as brahma_deposits_count',
            ])
            ->withSum('deposits as normal_deposits_amount', 'amount')
            ->withSum('brahmaDeposits as brahma_deposits_amount', 'amount')
            ->when($this->search, function ($q) {

                $q->where(function($query){

                    $query->where('name','like',"%{$this->search}%")
                        ->orWhere('username','like',"%{$this->search}%")
                        ->orWhere('email','like',"%{$this->search}%");

                });

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
        ])
            ->withCount([
                'deposits as normal_deposits_count',
                'brahmaDeposits as brahma_deposits_count',
            ])
            ->withSum('deposits as normal_deposits_amount', 'amount')
            ->withSum('brahmaDeposits as brahma_deposits_amount', 'amount')
            ->findOrFail($id);
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
