<?php

namespace App\Livewire\Admin;

use Livewire\Component;

class Players extends Component
{
    public function render()
    {
        return view('livewire.admin.players')
            ->layout('layouts.private');
    }
}
