<?php

namespace App\Livewire\Public;

use App\Mail\ContactLeadMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class FooterContact extends Component
{
    public string $email = '';

    public string $subject = '';

    public string $message = '';

    public bool $sent = false;


    public function send()
    {
        if (auth()->check()) {

            $this->validate([
                'subject' => ['required'],
                'message' => ['required'],
            ]);

            $user = auth()->user();

            Mail::to('admin@brahmabullgaming.com')
                ->send(
                    new ContactLeadMail(
                        $user->name,
                        $user->email,
                        $this->subject,
                        $this->message,
                        $user->hasRole('agent')
                            ? 'agent'
                            : 'player'
                    )
                );

            $this->reset([
                'subject',
                'message'
            ]);
        }

        else {

            $this->validate([
                'email' => ['required', 'email']
            ]);

            Mail::to('admin@brahmabullgaming.com')
                ->send(
                    new ContactLeadMail(
                        null,
                        $this->email,
                        null,
                        null,
                        'guest'
                    )
                );

            $this->reset('email');
        }

        $this->sent = true;
    }
    public function hideSuccess()
    {
        $this->sent = false;
    }
    public function render()
    {
        return view('livewire.public.footer-contact');
    }
}
