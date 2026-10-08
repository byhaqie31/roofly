<?php

namespace App\Notifications;

use App\Models\Agreement;
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
        $rent = 'RM ' . number_format($this->rentCents / 100, 2);

        return (new MailMessage)
            ->subject('Your tenancy agreement is ready to review')
            ->line("Hi {$notifiable->name}, your landlord has sent you the tenancy agreement for {$this->propertyLabel} ({$rent} per month) to review.")
            ->line('Tuan rumah anda telah menghantar perjanjian sewa untuk semakan anda.')
            ->action('Review agreement', $this->url())
            ->line('You can agree, or ask for changes with a note. / Anda boleh bersetuju, atau minta perubahan dengan nota.');
    }
}
