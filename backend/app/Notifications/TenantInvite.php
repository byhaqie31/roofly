<?php

namespace App\Notifications;

use App\Services\TenantInvites;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tenant invite — the link lands on the Nuxt app's /auth/accept-invite, not the API host. */
class TenantInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $plainToken,
        public readonly ?string $inviterName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(object $notifiable): string
    {
        return TenantInvites::urlFor($notifiable, $this->plainToken);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days    = TenantInvites::INVITE_DAYS;
        $subject = $this->inviterName
            ? "{$this->inviterName} invited you to Roofly"
            : 'You have been invited to Roofly';
        $intro = $this->inviterName
            ? "Hi {$notifiable->name}, {$this->inviterName} has set up your tenancy on Roofly, where you can see your rent, pay it and report issues."
            : "Hi {$notifiable->name}, your landlord has set up your tenancy on Roofly, where you can see your rent, pay it and report issues.";

        return (new MailMessage)
            ->subject($subject)
            ->line($intro)
            ->line('Tuan rumah anda telah menyediakan penyewaan anda di Roofly — lihat sewa, bayar dan laporkan masalah di satu tempat.')
            ->action('Set your password', $this->url($notifiable))
            ->line("This link expires in {$days} days. / Pautan ini tamat tempoh dalam {$days} hari.")
            ->line('If you were not expecting this, you can ignore this email. / Jika anda tidak menjangkakannya, abaikan e-mel ini.');
    }
}
