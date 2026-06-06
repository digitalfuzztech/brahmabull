<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Game;
use Illuminate\Support\Facades\Storage;
use App\Models\Notification;

class Games extends Component
{
    use WithFileUploads;

    public $showAddModal = false;

    public $name, $game_url, $image;

    public function getGamesProperty()
    {
        return Game::latest()->get();
    }
    public function toggleGame($id)
    {
        $game = Game::findOrFail($id);

        $game->is_active = !$game->is_active;
        $game->save();

        $status = $game->is_active ? 'enabled' : 'disabled';

        $admins = \App\Models\User::role('admin')->get();
        $agents = \App\Models\User::role('agent')->get();

        $receivers = $admins->merge($agents);

        foreach ($receivers as $receiver) {

            \App\Models\Notification::create([
                'user_id' => $receiver->id,

                'type' => 'game',

                'title' => 'Game Updated',

                'message' => "{$game->name} has been {$status} by " . auth()->user()->name,

                'action_text' => 'Got It!',

                'action_url' => $receiver->hasRole('admin')
                    ? route('admin.games')
                    : route('agent.games'),
            ]);
        }
    }

    public function createGame()
    {
        $this->validate([
            'name' => 'required',
            'game_url' => 'required',
            'image' => 'required|image|max:5120',
        ]);

        $path = $this->image->store('games', 'public');

        Game::create([
            'name' => $this->name,
            'game_url' => $this->game_url,
            'image' => $path,
            'is_active' => true,
        ]);


// NOTIFICATION (GAME CREATED)
        $admins = \App\Models\User::role('admin')->pluck('id');
        $agents = \App\Models\User::role('agent')->pluck('id');

        $receivers = $admins->merge($agents);

        foreach ($receivers as $userId) {

            \App\Models\Notification::create([
                'user_id' => $userId,
                'type' => 'game',
                'title' => 'Game Added',
                'message' => "Game {$this->name} has been added by " . auth()->user()->name,
                'action_text' => 'Got It!',
                'action_url' => $userId == auth()->id()
                    ? (auth()->user()->hasRole('admin')
                        ? '/admin/games'
                        : '/agent/games')
                    : null,
            ]);

        }
        $this->reset(['name','game_url','image','showAddModal']);
    }

    public function render()
    {
        return view('livewire.admin.games')
        ->layout('layouts.private');
    }
}
