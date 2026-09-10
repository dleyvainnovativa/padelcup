<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

/**
 * Branded Spanish password-reset email for Voleo.
 *
 * Extends Laravel's base ResetPassword so token creation / expiry / throttling
 * stay exactly as Fortify expects — we only change how the mail is rendered,
 * pointing it at our branded Blade template (emails.reset-password).
 */
class ResetPasswordNotification extends BaseResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);
        $minutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        return (new MailMessage)
            ->subject('Restablece tu contraseña · Voleo')
            ->view('emails.reset-password', [
                'url' => $url,
                'minutes' => $minutes,
                'name' => $notifiable->name ?? null,
            ]);
    }

    /**
     * Build the reset URL the same way the base class does (respects
     * Fortify/config route + the createUrlUsing hook if set).
     */
    protected function resetUrl($notifiable): string
    {
        if (static::$createUrlCallback) {
            return call_user_func(static::$createUrlCallback, $notifiable, $this->token);
        }

        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
