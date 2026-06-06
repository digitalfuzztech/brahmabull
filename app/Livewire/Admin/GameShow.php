<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Game;
use App\Models\Deposit;
use App\Models\Cashout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;

class GameShow extends Component
{
    use WithFileUploads;
    public $game;

    public $name;
    public $game_url;
    public $image;
    public $new_image;

    public function mount($game)
    {
        $this->game = Game::findOrFail($game);

        $this->name = $this->game->name;
        $this->game_url = $this->game->game_url;
    }

    public function updateGame()
    {
        $this->validate([
            'name' => 'required',
            'game_url' => 'required',
            'image' => 'nullable|image|max:5120',
        ]);

        $data = [
            'name' => $this->name,
            'game_url' => $this->game_url,
        ];

        // handle image update
        if ($this->image) {
            $path = $this->image->store('games', 'public');
            $data['image'] = $path;
        }

        $this->game->update($data);

        // refresh model so UI updates instantly
        $this->game = $this->game->fresh();

        session()->flash('success', 'Game updated successfully');
    }

    public function getDepositsProperty()
    {
        return Deposit::where('game_id', $this->game->id)
            ->where('status', 'verified')
            ->latest()
            ->get();
    }

    public function getCashoutsProperty()
    {
        return Cashout::where('game_id', $this->game->id)
            ->where('status', 'paid')
            ->latest()
            ->get();
    }

    public function getTopPlayersProperty()
    {
        return DB::table('deposits')
            ->select(
                'user_id',
                DB::raw('COUNT(id) as total_deposits'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->where('game_id', $this->game->id)
            ->where('status', 'verified')
            ->groupBy('user_id')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.game-show')
            ->layout('layouts.private');
    }
}
