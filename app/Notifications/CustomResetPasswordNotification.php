<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomResetPasswordNotification extends Notification
{
    public function __construct(public string $url) {}

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Reset Your BrahmaBull Password')
            ->greeting('Hello from BrahmaBull Gaming')
            ->line('We received a request to reset your password.')
            ->action('Reset Password', $this->url)
            ->line('If you did not request this, ignore this email.');
    }
}
