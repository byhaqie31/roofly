<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To a waitlist lead, sent by an admin from Enquiries: you're invited to sign
 * up. The button points at config('app.invite_signup_url') — this environment's
 * own register page unless INVITE_SIGNUP_URL overrides it (production → UAT's
 * while sign-up is held) — with ?email=… to prefill the form.
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
                'ctaUrl'   => $this->signupUrl(),
                'ctaLabel' => 'Create your Roofly account',
            ]);
    }

    private function signupUrl(): string
    {
        $url = config('app.invite_signup_url');

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query(['email' => $this->email]);
    }
}
