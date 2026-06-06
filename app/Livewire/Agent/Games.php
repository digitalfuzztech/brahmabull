<?php

namespace App\Livewire\Agent;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Game;


class Games extends Component
{
    use WithFileUploads;

    public $showAddModal = false;

    public $name, $game_url, $image;

    public function getGamesProperty()
    {
        return Game::latest()->get();
    }




    public function render()
    {
        return view('livewire.agent.games')
            ->layout('layouts.private');
    }
}
