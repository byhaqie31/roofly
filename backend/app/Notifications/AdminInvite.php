<?php
// backend/app/Notifications/AdminInvite.php
namespace App\Notifications;

use App\Support\NoticeMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $plainToken) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(): string
    {
        return rtrim(config('app.frontend_url'), '/') . '/admin/accept-invite?token=' . $this->plainToken;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return NoticeMail::make('You have been invited to the Roofly admin portal', [
            'tag'            => 'ADMIN PORTAL',
            'pill'           => 'ADMIN ACCESS',
            'headlineLead'   => "You're invited to",
            'headlineAccent' => 'Roofly admin.',
            'greeting'       => NoticeMail::firstName($notifiable->name),
            'paragraphs'     => ["You've been given access to the Roofly admin portal. Set your password to sign in."],
            'malay'          => 'Anda telah diberi akses ke portal admin Roofly.',
            'ctaUrl'         => $this->url(),
            'ctaLabel'       => 'Set your password',
            'fineprint'      => ['This link expires in 7 days. / Pautan ini tamat tempoh dalam 7 hari.'],
            'reason'         => "You're receiving this because you were invited to the Roofly admin portal.",
        ]);
    }
}
