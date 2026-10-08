<?php
// backend/app/Notifications/OwnerWarning.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Payment-warning notice to an owner (spec § 8). SP1 ships mail only; SP2
 * adds whatsapp/sms by extending via() to read the owner's enabled channels
 * — callers (OwnerController::warn) never change.
 */
class OwnerWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public const TEMPLATE_PAYMENT_OVERDUE = 'payment_overdue';
    public const TEMPLATES = [self::TEMPLATE_PAYMENT_OVERDUE];

    public function __construct(
        public readonly string $template,
        public readonly string $suspendOn,
        public readonly ?string $extraLine = null,
    ) {}

    public static function text(string $template, string $suspendOn, ?string $extraLine = null): string
    {
        $body = match ($template) {
            self::TEMPLATE_PAYMENT_OVERDUE => "Your Roofly subscription payment is overdue; your account will be suspended on {$suspendOn} unless settled.",
        };

        return $extraLine ? "{$body}\n\n{$extraLine}" : $body;
    }

    public function via(object $notifiable): array
    {
        return ['mail']; // SP2: read $notifiable->notification_preferences['channels']
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lines = explode("\n\n", self::text($this->template, $this->suspendOn, $this->extraLine));

        $mail = (new MailMessage)->subject('Action needed: your Roofly subscription');
        foreach ($lines as $line) {
            $mail->line($line);
        }

        return $mail->line('If you have already settled this, you can ignore this notice.');
    }
}
