<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * To a newly registered owner (password sign-up or first Google sign-in).
 * Branded template: resources/views/emails/owner-welcome(-text).blade.php.
 */
class OwnerWelcome extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $firstName = Str::before(trim((string) $notifiable->name), ' ') ?: 'there';

        return (new MailMessage)
            ->subject('Welcome to Roofly')
            ->view(['emails.owner-welcome', 'emails.owner-welcome-text'], [
                'firstName' => $firstName,
                // The auth guard routes a not-yet-onboarded owner to /owner/onboarding.
                'ctaUrl'    => rtrim(config('app.frontend_url'), '/') . '/owner',
                'ctaLabel'  => 'Go to your dashboard',
            ]);
    }
}
