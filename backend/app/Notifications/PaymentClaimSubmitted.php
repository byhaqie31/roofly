<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
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
        $amount = 'RM ' . number_format($this->amountCents / 100, 2);

        return (new MailMessage)
            ->subject("{$this->tenantName} says they've paid {$this->invoiceNumber}")
            ->line("Hi {$notifiable->name}, {$this->tenantName} has marked invoice {$this->invoiceNumber} ({$amount}) as paid by bank transfer on {$this->paidOn}, reference {$this->reference}.")
            ->line("Penyewa anda menyatakan bayaran untuk invois {$this->invoiceNumber} telah dibuat. Sila semak akaun bank anda.")
            ->action('Review payment', $this->url())
            ->line('Check your bank account, then confirm or reject it. / Semak akaun bank anda, kemudian sahkan atau tolak.');
    }
}
