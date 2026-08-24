<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Customer (owner/tenant) password reset — link lands on the Nuxt app, not the API host. */
class ResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(object $notifiable): string
    {
        return rtrim(config('app.frontend_url'), '/')
            . '/auth/reset-password?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new MailMessage)
            ->subject('Reset your Roofly password')
            ->line("Hi {$notifiable->name}, we received a request to reset your password.")
            ->line('Kami menerima permintaan untuk menetapkan semula kata laluan anda.')
            ->action('Reset password', $this->url($notifiable))
            ->line("This link expires in {$minutes} minutes. / Pautan ini tamat tempoh dalam {$minutes} minit.")
            ->line('If you did not request this, you can ignore this email. / Jika anda tidak memintanya, abaikan e-mel ini.');
    }
}
