<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class Agents extends Component
{
    public $showAddModal = false;

    public $name, $email, $password, $phone, $role;

    public function getAgentsProperty()
    {
        return User::whereHas('roles', function ($q) {
            $q->where('name', 'agent');
        })->latest()->get();
    }

    public function toggleAgent($id)
    {
        $agent = User::findOrFail($id);

        $agent->is_active = !$agent->is_active;
        $agent->save();
    }

    public function createAgent()
    {
        $this->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'phone' => $this->phone,
            'is_active' => true,
            'role' => 'agent',
        ]);

        $user->assignRole('agent');

        $this->reset(['name','email','password','phone','showAddModal']);
    }

    public function render()
    {
        return view('livewire.admin.agents')
            ->layout('layouts.private');
    }
}
