<?php

namespace App\Notifications;

use App\Models\Agreement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the owner: the tenant agreed, or asked for changes. */
class AgreementReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly string $agreementId;
    public readonly string $tenantName;
    public readonly string $propertyLabel;

    public function __construct(Agreement $agreement, public readonly bool $accepted, public readonly ?string $note = null)
    {
        $this->agreementId   = $agreement->id;
        $this->tenantName    = $agreement->tenant?->name ?? 'Your tenant';
        $this->propertyLabel = trim(($agreement->unit?->property?->name ?? '') . ' · ' . ($agreement->unit?->label ?? ''), ' ·');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/owner/agreements/' . $this->agreementId;
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->accepted) {
            return (new MailMessage)
                ->subject("{$this->tenantName} agreed to the tenancy agreement")
                ->line("Hi {$notifiable->name}, {$this->tenantName} has agreed to the agreement for {$this->propertyLabel}.")
                ->line('Penyewa anda telah bersetuju dengan perjanjian sewa.')
                ->action('Activate agreement', $this->url())
                ->line('Activate it in Roofly to start the tenancy and the first rent invoice. / Aktifkan dalam Roofly untuk memulakan penyewaan dan invois sewa pertama.');
        }

        return (new MailMessage)
            ->subject("{$this->tenantName} asked for changes to the agreement")
            ->line("Hi {$notifiable->name}, {$this->tenantName} asked for changes to the agreement for {$this->propertyLabel}:")
            ->line('"' . $this->note . '"')
            ->line('Penyewa anda meminta perubahan kepada perjanjian sewa.')
            ->action('Edit and re-send', $this->url())
            ->line('The agreement is back in draft. / Perjanjian kini kembali kepada draf.');
    }
}
