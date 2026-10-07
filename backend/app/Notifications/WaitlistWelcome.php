<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To a brand-new waitlist lead: you're in. Sent on demand to the email alone —
 * the form collects no name, so the greeting stays generic.
 */
class WaitlistWelcome extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're on the Roofly waitlist 🎉")
            ->greeting('Hi there,')
            ->line("You're officially on the Roofly waitlist! Thanks for joining us — we're excited to have you here.")
            ->line("We're getting things ready and will email you when Roofly is ready to welcome you. There's nothing else you need to do for now.")
            ->line('Thanks for being part of our journey from the start.')
            ->line('Anda kini dalam senarai menunggu Roofly! Terima kasih kerana menyertai kami — kami akan e-mel anda sebaik sahaja Roofly sedia untuk anda. Buat masa ini, tiada apa-apa lagi yang perlu anda lakukan.')
            ->salutation("Warm regards,  \nThe Roofly Team"); // two trailing spaces = markdown line break
    }
}
