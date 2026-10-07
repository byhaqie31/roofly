<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To super admins: a new owner account was created (password register or first Google sign-in). */
class AdminNewOwnerSignup extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $ownerId,
        public string $name,
        public string $email,
        public ?string $phone,
        public string $method,
        public string $signedUpAt,
    ) {}

    public static function fromOwner(User $owner, string $method): self
    {
        return new self(
            $owner->id,
            (string) $owner->name,
            $owner->email,
            $owner->phone,
            $method,
            ($owner->created_at ?? now())->timezone('Asia/Kuala_Lumpur')->format('j M Y, g:i a') . ' MYT',
        );
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = "New owner sign-up: {$this->name}";

        return (new MailMessage)
            ->subject($subject)
            ->view(['emails.admin-alert', 'emails.admin-alert-text'], [
                'subject'        => $subject,
                'pill'           => 'NEW SIGN-UP',
                'headlineLead'   => 'A new owner',
                'headlineAccent' => 'joined.',
                'intro'          => 'Someone just created an owner account on Roofly.',
                'rows'           => array_filter([
                    'Name'      => $this->name,
                    'Email'     => $this->email,
                    'Phone'     => $this->phone,
                    'Signed up' => $this->method === 'google' ? 'With Google' : 'With email and password',
                    'When'      => $this->signedUpAt,
                ]),
                'ctaUrl'   => rtrim(config('app.frontend_url'), '/') . '/admin/owners/' . $this->ownerId,
                'ctaLabel' => 'View owner',
            ]);
    }
}
