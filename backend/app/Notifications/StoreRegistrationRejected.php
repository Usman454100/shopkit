<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StoreRegistrationRejected extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $businessName,
        private readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your \"{$this->businessName}\" registration request was not approved")
            ->line("Your request to register \"{$this->businessName}\" on ".config('app.name').' was not approved.')
            ->line("Reason: {$this->reason}");
    }
}
