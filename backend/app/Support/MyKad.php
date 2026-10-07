<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Malaysian MyKad number helpers. Canonical storage form is dashed
 * (`YYMMDD-PB-####`) — what the seed data, the owner forms and the frontend
 * display expect — but nobody should have to type the dashes, so every write
 * path normalises 12 bare digits (or any spacing) to it first.
 */
final class MyKad
{
    public const DIGITS = 12;

    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $input);
        if (strlen($digits) !== self::DIGITS) {
            return null;
        }

        return substr($digits, 0, 6) . '-' . substr($digits, 6, 2) . '-' . substr($digits, 8);
    }

    /**
     * YYMMDD → ISO date. Two-digit years take the current century unless that
     * would put the birthday in the future, in which case the previous one.
     */
    public static function dateOfBirth(?string $input, ?CarbonInterface $today = null): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $input);
        if (strlen($digits) < 6) {
            return null;
        }
        $today = CarbonImmutable::instance($today ?? now())->startOfDay();
        [$yy, $mm, $dd] = [(int) substr($digits, 0, 2), (int) substr($digits, 2, 2), (int) substr($digits, 4, 2)];
        if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) {
            return null;
        }

        $year = intdiv($today->year, 100) * 100 + $yy;
        if (checkdate($mm, $dd, $year) && CarbonImmutable::create($year, $mm, $dd)->startOfDay()->greaterThan($today)) {
            $year -= 100;
        } elseif (! checkdate($mm, $dd, $year)) {
            $year -= 100; // e.g. 29 Feb in a non-leap "current century" year
        }
        if (! checkdate($mm, $dd, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $mm, $dd);
    }

    /**
     * Normalise `personal.icNumber` in a request payload and default
     * `personal.dateOfBirth` from it when blank. Shared by every request that
     * writes the personal block; returns the array unchanged when there is no
     * usable number, so free-text values keep the old "stored verbatim" behaviour.
     *
     * @param array<string, mixed>|null $personal
     * @return array<string, mixed>|null
     */
    public static function applyToPersonal(?array $personal): ?array
    {
        if ($personal === null) {
            return null;
        }
        $normalized = self::normalize($personal['icNumber'] ?? null);
        if ($normalized === null) {
            return $personal;
        }
        $personal['icNumber'] = $normalized;
        if (blank($personal['dateOfBirth'] ?? null)) {
            $personal['dateOfBirth'] = self::dateOfBirth($normalized);
        }

        return $personal;
    }
}
