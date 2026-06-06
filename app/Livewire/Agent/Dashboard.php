<?php

namespace App\Livewire\Agent;

use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.agent.dashboard')
            ->layout('layouts.private');
    }
}
