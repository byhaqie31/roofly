<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To a brand-new waitlist lead: you're on the list. Sent on demand to the email
 * alone — the form collects no name, so the greeting stays generic. Branded
 * template: resources/views/emails/waitlist-confirmation(-text).blade.php.
 */
class WaitlistConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Waitlist confirmation: you\'re on the Roofly list')
            ->view(['emails.waitlist-confirmation', 'emails.waitlist-confirmation-text'], [
                'ctaUrl'   => config('app.demo_url'), // env DEMO_URL
                'ctaLabel' => 'Explore the demo',
            ]);
    }
}
