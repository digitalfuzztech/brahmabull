<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\User;
use App\Models\Deposit;
use App\Models\Cashout;
use App\Models\Wallet;
use Illuminate\Support\Facades\Hash;
use App\Models\Notification;

class AgentShow extends Component
{
    public $agent;
    public $showEditModal = false;

    public $name;
    public $email;
    public $password = '';
    public $successMessage = null;
    public function mount($id)
    {
        $this->agent = User::findOrFail($id);
    }

    public function getDepositsProperty()
    {
        return Deposit::where('verified_by', $this->agent->id)
            ->latest()
            ->get();
    }

    public function getCashoutsProperty()
    {
        return Cashout::where('verified_by', $this->agent->id)
            ->latest()
            ->get();
    }

    public function getWalletsProperty()
    {
        return Wallet::where('created_by', $this->agent->id)
            ->latest()
            ->get();
    }
    public function openEditModal()
    {
        $this->name = $this->agent->name;
        $this->email = $this->agent->email;
        $this->password = '';

        $this->showEditModal = true;
    }
    public function closeEditModal()
    {
        $this->showEditModal = false;
    }
    public function updateAgent()
    {
        $this->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'password' => 'nullable|min:6',
        ]);

        $oldName = $this->agent->name;
        $oldEmail = $this->agent->email;

        $data = [
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($this->password) {
            $data['password'] = Hash::make($this->password);
        }

        $this->agent->update($data);

        $admins = User::role('admin')->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'agent_updated',
                'title' => 'Agent Updated',
                'message' =>
                    "Agent {$oldName} updated to {$this->name} ({$this->email})",
            ]);
        }

        Notification::create([
            'user_id' => $this->agent->id,
            'type' => 'profile_updated',
            'title' => 'Profile Updated',
            'message' =>
                "Your profile has been updated. Name: {$this->name}, Email: {$this->email}",
        ]);

        $this->showEditModal = false;
        $this->successMessage = "Agent Updated Successfully";

        $this->dispatch('agent-updated');
        session()->flash('success', 'Agent updated successfully.');
    }
    public function render()
    {
        return view('livewire.admin.agent-show')
            ->layout('layouts.private');
    }
}
