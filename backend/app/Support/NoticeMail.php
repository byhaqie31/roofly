<?php

namespace App\Support;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * A transactional email on the shared Roofly shell: emails/notice(-text).blade.php
 * on top of emails/layout. Notifications pass the copy; every key but `tag`,
 * `headlineLead`, `headlineAccent` and `reason` is optional:
 *   tag, pill            header label / orange pill (UPPER CASE)
 *   headlineLead/Accent  two-line <h1>, the accent half in Roofly orange
 *   greeting             first name → "Hi Aminah,"
 *   paragraphs           English body copy, one string per paragraph
 *   rows                 label => value details table (nulls dropped)
 *   note                 ['label' => …, 'text' => …] — quoted user-written text
 *   malay                the Bahasa Melayu paragraph, muted under the English
 *   ctaUrl, ctaLabel     the button
 *   closing              paragraph after the button
 *   fineprint            small lines above "Button not working?" (expiry, ignore-me)
 *   reason               footer "You're receiving this because…" line
 */
class NoticeMail
{
    private const DEFAULTS = [
        'pill'       => null,
        'greeting'   => null,
        'paragraphs' => [],
        'rows'       => [],
        'note'       => null,
        'malay'      => null,
        'ctaUrl'     => null,
        'ctaLabel'   => null,
        'closing'    => null,
        'fineprint'  => [],
    ];

    public static function make(string $subject, array $data): MailMessage
    {
        $data['rows'] = array_filter($data['rows'] ?? [], fn ($v) => $v !== null && $v !== '');

        return (new MailMessage)
            ->subject($subject)
            ->view(['emails.notice', 'emails.notice-text'], ['subject' => $subject] + $data + self::DEFAULTS);
    }

    /** "Aminah binti Yusof" → "Aminah"; a blank name → "there". */
    public static function firstName(?string $name): string
    {
        return Str::before(trim((string) $name), ' ') ?: 'there';
    }
}
