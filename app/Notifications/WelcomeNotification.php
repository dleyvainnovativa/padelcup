<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Branded Spanish welcome email, sent once when a player self-registers
 * (password signup via CreateNewUser, or social signup via OAuthController).
 * NOT sent for manager/admin-created or seeded accounts.
 *
 * Implements ShouldQueue is intentionally omitted — the app runs on shared
 * hosting with a database queue; if you later want it async, add
 * `implements ShouldQueue` and `use Queueable;` dispatch stays the same.
 */
class WelcomeNotification extends Notification
{
    public function __construct(private bool $viaSocial = false) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('¡Bienvenido a Voleo!')
            ->view('emails.welcome', [
                'name' => $notifiable->name ?? null,
                'viaSocial' => $this->viaSocial,
                'dashboardUrl' => route('dashboard'),
                'directoryUrl' => route('public.directory'),
            ]);
    }
}
