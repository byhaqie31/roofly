<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To a waitlist lead, sent by an admin from Enquiries: you're invited to sign
 * up. The button points at this environment's FRONTEND_URL/auth/register?email=….
 * Template: resources/views/emails/waitlist-invitation(-text).blade.php.
 */
class WaitlistInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $email) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Roofly invitation is here')
            ->view(['emails.waitlist-invitation', 'emails.waitlist-invitation-text'], [
                // Prefills the register form's email field.
                'ctaUrl'   => rtrim(config('app.frontend_url'), '/') . '/auth/register?' . http_build_query(['email' => $this->email]),
                'ctaLabel' => 'Create your Roofly account',
            ]);
    }
}
