<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To super admins: a brand-new enquiry landed in Enquiries (sent via SuperAdminAlerts). */
class AdminNewEnquiry extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $email, public string $source, public string $receivedAt) {}

    public static function make(string $email, string $source = 'waitlist', ?CarbonInterface $at = null): self
    {
        return new self($email, $source, ($at ?? now())->timezone('Asia/Kuala_Lumpur')->format('j M Y, g:i a') . ' MYT');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = "New enquiry: {$this->email}";

        return (new MailMessage)
            ->subject($subject)
            ->view(['emails.admin-alert', 'emails.admin-alert-text'], [
                'subject'        => $subject,
                'pill'           => 'NEW ENQUIRY',
                'headlineLead'   => 'Someone wants',
                'headlineAccent' => 'in.',
                'intro'          => 'A new enquiry just landed in Enquiries.',
                'rows'           => [
                    'Email'    => $this->email,
                    'From'     => $this->source === 'waitlist' ? 'Coming-soon waitlist' : ucfirst($this->source),
                    'Received' => $this->receivedAt,
                ],
                'ctaUrl'   => rtrim(config('app.frontend_url'), '/') . '/admin/enquiries',
                'ctaLabel' => 'Open Enquiries',
            ]);
    }
}
