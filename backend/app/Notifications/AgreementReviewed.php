<?php

namespace App\Notifications;

use App\Models\Agreement;
use App\Support\NoticeMail;
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
        $shared = [
            'tag'      => 'TENANCY AGREEMENT',
            'greeting' => NoticeMail::firstName($notifiable->name),
            'ctaUrl'   => $this->url(),
            'reason'   => "You're receiving this because you manage this tenancy on Roofly.",
        ];

        if ($this->accepted) {
            return NoticeMail::make("{$this->tenantName} agreed to the tenancy agreement", $shared + [
                'pill'           => 'AGREED BY TENANT',
                'headlineLead'   => 'Your agreement is',
                'headlineAccent' => 'accepted.',
                'paragraphs'     => [
                    "{$this->tenantName} has agreed to the agreement for {$this->propertyLabel}.",
                    'Activate it in Roofly to start the tenancy and the first rent invoice.',
                ],
                'malay'    => 'Penyewa anda telah bersetuju dengan perjanjian sewa. Aktifkan dalam Roofly untuk memulakan penyewaan dan invois sewa pertama.',
                'ctaLabel' => 'Activate agreement',
            ]);
        }

        return NoticeMail::make("{$this->tenantName} asked for changes to the agreement", $shared + [
            'pill'           => 'CHANGES REQUESTED',
            'headlineLead'   => 'Your tenant asked',
            'headlineAccent' => 'for changes.',
            'paragraphs'     => ["{$this->tenantName} asked for changes to the agreement for {$this->propertyLabel}."],
            'note'           => ['label' => "{$this->tenantName}'s note", 'text' => (string) $this->note],
            'malay'          => 'Penyewa anda meminta perubahan kepada perjanjian sewa. Perjanjian kini kembali kepada draf.',
            'ctaLabel'       => 'Edit and re-send',
            'closing'        => 'The agreement is back in draft.',
        ]);
    }
}
