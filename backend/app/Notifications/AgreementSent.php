<?php

namespace App\Notifications;

use App\Models\Agreement;
use App\Support\NoticeMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the tenant: your landlord sent you an agreement to review. */
class AgreementSent extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly string $agreementId;
    public readonly string $propertyLabel;
    public readonly int $rentCents;

    public function __construct(Agreement $agreement)
    {
        // Scalars only — queued notifications shouldn't drag whole models through the queue.
        $this->agreementId   = $agreement->id;
        $this->propertyLabel = trim(($agreement->unit?->property?->name ?? '') . ' · ' . ($agreement->unit?->label ?? ''), ' ·');
        $this->rentCents     = (int) $agreement->rent_amount_cents;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/tenant/agreement';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return NoticeMail::make('Your tenancy agreement is ready to review', [
            'tag'            => 'TENANCY AGREEMENT',
            'pill'           => 'READY TO REVIEW',
            'headlineLead'   => 'Your agreement is',
            'headlineAccent' => 'ready to review.',
            'greeting'       => NoticeMail::firstName($notifiable->name),
            'paragraphs'     => ['Your landlord has sent you the tenancy agreement to review.'],
            'rows'           => [
                'Property'     => $this->propertyLabel,
                'Monthly rent' => 'RM ' . number_format($this->rentCents / 100, 2),
            ],
            'malay'    => 'Tuan rumah anda telah menghantar perjanjian sewa untuk semakan anda. Anda boleh bersetuju, atau minta perubahan dengan nota.',
            'ctaUrl'   => $this->url(),
            'ctaLabel' => 'Review agreement',
            'closing'  => 'You can agree, or ask for changes with a note.',
            'reason'   => "You're receiving this because your landlord manages your tenancy on Roofly.",
        ]);
    }
}
