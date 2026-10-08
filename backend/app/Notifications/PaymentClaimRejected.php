<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the tenant: the landlord couldn't match your transfer (spec 2026-10-08 § 4). */
class PaymentClaimRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly string $paymentId;
    public readonly string $invoiceNumber;
    public readonly string $reference;
    public readonly string $reason;

    public function __construct(Payment $payment, Invoice $invoice)
    {
        // Scalars only — queued notifications shouldn't drag whole models through the queue.
        $this->paymentId     = $payment->id;
        $this->invoiceNumber = $invoice->invoice_number;
        $this->reference     = (string) $payment->reference;
        $this->reason        = (string) $payment->rejection_reason;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/tenant/payments';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Your payment for {$this->invoiceNumber} wasn't confirmed")
            ->line("Hi {$notifiable->name}, your landlord couldn't confirm the transfer you reported for invoice {$this->invoiceNumber} (reference {$this->reference}).")
            ->line("Reason: {$this->reason}")
            ->line("Tuan rumah anda tidak dapat mengesahkan bayaran untuk invois {$this->invoiceNumber}.")
            ->action('View invoice', $this->url())
            ->line('Check the details and report the payment again. / Semak butiran dan laporkan bayaran sekali lagi.');
    }
}
