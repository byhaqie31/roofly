<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Support\NoticeMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the owner: a tenant says they've paid by transfer — confirm or reject (spec 2026-10-08 § 4). */
class PaymentClaimSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly string $paymentId;
    public readonly string $invoiceNumber;
    public readonly string $tenantName;
    public readonly int $amountCents;
    public readonly string $reference;
    public readonly string $paidOn;

    public function __construct(Payment $payment, Invoice $invoice, string $tenantName)
    {
        // Scalars only — queued notifications shouldn't drag whole models through the queue.
        $this->paymentId     = $payment->id;
        $this->invoiceNumber = $invoice->invoice_number;
        $this->tenantName    = $tenantName;
        $this->amountCents   = (int) $payment->amount_cents;
        $this->reference     = (string) $payment->reference;
        $this->paidOn        = $payment->paid_at?->format('j M Y') ?? '';
    }

    /** The owner's "payment received" email preference — on unless explicitly switched off. */
    public static function wantedBy(User $owner): bool
    {
        return ($owner->notification_preferences['events']['payment_received'] ?? true) !== false;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/owner/payments?status=awaiting';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return NoticeMail::make("{$this->tenantName} says they've paid {$this->invoiceNumber}", [
            'tag'            => 'RENT PAYMENT',
            'pill'           => 'AWAITING CONFIRMATION',
            'headlineLead'   => NoticeMail::firstName($this->tenantName) . ' says',
            'headlineAccent' => "they've paid.",
            'greeting'       => NoticeMail::firstName($notifiable->name),
            'paragraphs'     => [
                "{$this->tenantName} has marked invoice {$this->invoiceNumber} as paid by bank transfer.",
                'Check your bank account, then confirm or reject it.',
            ],
            'rows' => [
                'Invoice'   => $this->invoiceNumber,
                'Amount'    => 'RM ' . number_format($this->amountCents / 100, 2),
                'Paid on'   => $this->paidOn,
                'Reference' => $this->reference,
            ],
            'malay'    => "Penyewa anda menyatakan bayaran untuk invois {$this->invoiceNumber} telah dibuat. Sila semak akaun bank anda, kemudian sahkan atau tolak.",
            'ctaUrl'   => $this->url(),
            'ctaLabel' => 'Review payment',
            'reason'   => "You're receiving this because payment emails are on in your Roofly notification settings.",
        ]);
    }
}
