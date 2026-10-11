<?php

namespace App\Support;

/**
 * The legal footer under every email — emails/layout (html) and
 * emails/partials/legal-footer-text (plain text). Operator + contact details
 * come from config/legal.php; anything unset drops out.
 */
class EmailFooter
{
    /** @return array<string, string> label => href (the public /legal/* pages, then a mailto:) */
    public static function links(): array
    {
        $base  = rtrim((string) config('app.frontend_url'), '/');
        $links = ['Privacy' => "{$base}/legal/privacy", 'Terms' => "{$base}/legal/terms"];

        if ($email = config('legal.contact.email')) {
            $links['Contact us'] = "mailto:{$email}";
        }

        return $links;
    }

    /** "© 2026 Axel Nova Ventures · SSM 202603119899 (…) · I-City, …" */
    public static function operatorLine(): string
    {
        $ssm = config('legal.operator.ssm_number');

        return implode(' · ', array_filter([
            '© ' . now()->year . ' ' . config('legal.operator.name'),
            $ssm ? "SSM {$ssm}" : null,
            config('legal.operator.registered_address'),
        ]));
    }
}
