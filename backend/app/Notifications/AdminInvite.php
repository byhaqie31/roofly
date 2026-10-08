<?php
// backend/app/Notifications/AdminInvite.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $plainToken) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/admin/accept-invite?token=' . $this->plainToken;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been invited to the Roofly admin portal')
            ->line("Hi {$notifiable->name}, you've been given access to the Roofly admin portal.")
            ->line('Anda telah diberi akses ke portal admin Roofly.')
            ->action('Set your password', $this->url())
            ->line('This link expires in 7 days. / Pautan ini tamat tempoh dalam 7 hari.');
    }
}
