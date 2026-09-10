<?php

namespace App\Notifications;

use App\Models\PlayerClaim;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells a player their profile claim was approved or rejected.
 */
class PlayerClaimReviewedNotification extends Notification
{
    public function __construct(private PlayerClaim $claim) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $approved = $this->claim->status === PlayerClaim::APPROVED;

        return (new MailMessage)
            ->subject($approved
                ? 'Tu perfil de jugador fue aprobado · Voleo'
                : 'Sobre tu solicitud de perfil · Voleo')
            ->view('emails.claim-reviewed', [
                'name' => $notifiable->name ?? null,
                'approved' => $approved,
                'reason' => $this->claim->review_note,
                'dashboardUrl' => route('player.dashboard'),
            ]);
    }
}
