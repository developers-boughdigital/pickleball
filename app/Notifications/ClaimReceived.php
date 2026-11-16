<?php

namespace App\Notifications;

use App\Models\ListingClaim;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "We've got your claim": sent to the claimant straight after they send it. */
class ClaimReceived extends Notification
{
    public function __construct(public ListingClaim $claim) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->claim->claimable->name;

        return (new MailMessage)
            ->subject("We’ve got your claim for {$name}")
            ->greeting("Thanks, {$notifiable->name}.")
            ->line("You’ve asked to manage {$name} on ".config('app.name').'.')
            ->line('Someone on our team checks every claim by hand, usually within two working days. We’ll email you either way.')
            ->action('See your claims', route('account'));
    }
}
