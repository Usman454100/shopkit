<?php

namespace App\Notifications;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StoreApprovalInvite extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Store $store,
        private readonly string $inviteToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Points at the API for now — admin-web doesn't exist yet (backend-only
        // milestone). Swap for the real admin-web invite-acceptance page once it does.
        $inviteUrl = rtrim(config('app.url'), '/')."/api/invite/{$this->inviteToken}";

        return (new MailMessage)
            ->subject("Your store \"{$this->store->name}\" has been approved")
            ->greeting("Welcome to ".config('app.name').", {$notifiable->name}!")
            ->line("Your store \"{$this->store->name}\" has been approved.")
            ->line('Use the link below to set your password and activate your account.')
            ->action('Set your password', $inviteUrl)
            ->line('This link expires in 7 days.');
    }
}
