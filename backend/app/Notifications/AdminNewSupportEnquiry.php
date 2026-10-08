<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** To super admins: an owner or tenant sent a message from the in-app help button. */
class AdminNewSupportEnquiry extends Notification implements ShouldQueue
{
    use Queueable;

    private const TYPE_LABELS = ['issue' => 'Issue', 'feedback' => 'Feedback', 'question' => 'Question'];

    public function __construct(
        public string $type,
        public string $name,
        public string $email,
        public ?string $role,
        public string $message,
        public ?string $pageUrl,
        public string $receivedAt,
        public ?string $pageLabel = null,
    ) {}

    public static function fromEnquiry(Enquiry $e): self
    {
        return new self(
            $e->type, $e->name, $e->email, $e->role, $e->message, $e->page_url,
            ($e->created_at ?? now())->timezone('Asia/Kuala_Lumpur')->format('j M Y, g:i a') . ' MYT',
            $e->page_label,
        );
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = self::TYPE_LABELS[$this->type] ?? ucfirst($this->type);
        $subject = "{$label} from {$this->name}: " . Str::limit($this->message, 50);

        return (new MailMessage)
            ->subject($subject)
            ->replyTo($this->email, $this->name)
            ->view(['emails.admin-alert', 'emails.admin-alert-text'], [
                'subject'        => $subject,
                'pill'           => 'NEW ' . strtoupper($label),
                'headlineLead'   => 'New message from',
                'headlineAccent' => Str::before($this->name, ' ') . '.',
                'intro'          => Str::limit($this->message, 600),
                'rows'           => array_filter([
                    'From'     => $this->name . ($this->role ? ' (' . $this->role . ')' : ''),
                    'Email'    => $this->email,
                    'Type'     => $label,
                    'Sent from' => $this->pageLabel && $this->pageUrl ? "{$this->pageLabel} ({$this->pageUrl})" : ($this->pageLabel ?? $this->pageUrl),
                    'Received' => $this->receivedAt,
                ]),
                'ctaUrl'   => rtrim(config('app.frontend_url'), '/') . '/admin/enquiries?tab=messages',
                'ctaLabel' => 'Open Messages',
            ]);
    }
}
