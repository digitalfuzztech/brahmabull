<?php

namespace App\Livewire\Public;

use Livewire\Component;
use App\Models\Game;

class HomePage extends Component
{
    public function render()
    {
        return view('livewire.public.home-page', [
            'games' => Game::where('is_active', true)->get(),
        ])->layout('layouts.public');
    }
}
