<?php

namespace App\Notifications;

use App\Models\ListingClaim;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The claim was approved (with a link to edit the listing) or turned down (with the reason). */
class ClaimDecided extends Notification
{
    public function __construct(public ListingClaim $claim) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $listing = $this->claim->claimable;

        if ($this->claim->status === 'approved') {
            return (new MailMessage)
                ->subject("You now manage {$listing->name}")
                ->greeting("Good news, {$notifiable->name}.")
                ->line("Your claim was approved. You can now update {$listing->name}’s details, and your changes go live straight away.")
                ->action('Edit your listing', route('manage.edit', [$this->claim->kind(), $listing]));
        }

        return (new MailMessage)
            ->subject("About your claim for {$listing->name}")
            ->greeting("Hi {$notifiable->name},")
            ->line("We couldn’t approve your claim for {$listing->name}.")
            ->line('Reason: '.$this->claim->reject_reason)
            ->line('If you can show us more, for example from a club email address or a committee page that lists you, you’re welcome to claim it again.')
            ->action('Back to the listing', $this->claim->kind() === 'club' ? route('explore.club', $listing) : route('explore.venue', $listing));
    }
}
