<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Writes tracked events and keeps the leads table in step (spec § 4).
 * The only PII stored is the email a person typed; IP is salted-hashed.
 */
final class AnalyticsRecorder
{
    public const MAX_PROPS_BYTES = 2048;

    public static function hashIp(?string $ip): ?string
    {
        return $ip === null ? null : hash('sha256', $ip . config('app.key'));
    }

    /** @param array{visitorId:string,event:string,path?:?string,referrer?:?string,utm?:?array,props?:?array} $payload */
    public function record(array $payload, ?string $ip, ?string $userAgent): AnalyticsEvent
    {
        $event = $payload['event'] ?? '';
        if (! in_array($event, AnalyticsEvent::EVENTS, true)) {
            throw new InvalidArgumentException("Unknown analytics event: {$event}");
        }
        if (! Str::isUuid($payload['visitorId'] ?? '')) {
            throw new InvalidArgumentException('visitorId must be a uuid');
        }
        $props = $payload['props'] ?? null;
        if ($props !== null && strlen(json_encode($props)) > self::MAX_PROPS_BYTES) {
            throw new InvalidArgumentException('props too large');
        }

        $row = AnalyticsEvent::create([
            'visitor_id' => $payload['visitorId'],
            'event'      => $event,
            'path'       => isset($payload['path']) ? Str::limit((string) $payload['path'], 255, '') : null,
            'referrer'   => isset($payload['referrer']) ? Str::limit((string) $payload['referrer'], 255, '') : null,
            'utm'        => $payload['utm'] ?? null,
            'props'      => $props,
            'ip_hash'    => self::hashIp($ip),
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
            'created_at' => now(),
        ]);

        $email = isset($props['email']) ? Str::lower(trim((string) $props['email'])) : null;
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Client-supplied props.userId is never trusted for conversion — only
            // linkRegistration() (the server's own trusted register flow) may set
            // converted_user_id, using the authenticated user's own id.
            $this->touchLead($email, $payload['visitorId'], $event === 'register' ? 'register' : 'waitlist', null);
        } elseif ($event === 'demo_enter') {
            Lead::where('visitor_id', $payload['visitorId'])->update(['last_seen_at' => now()]);
        }

        return $row;
    }

    public function linkRegistration(User $user, ?string $visitorId = null): void
    {
        $this->touchLead(Str::lower($user->email), $visitorId, 'register', $user->id);
    }

    private function touchLead(string $email, ?string $visitorId, string $sourceIfNew, ?string $convertedUserId): void
    {
        $lead = Lead::firstOrNew(['email' => $email]);
        if (! $lead->exists) {
            $lead->source = $sourceIfNew;
            $lead->first_seen_at = now();
        }
        $lead->visitor_id ??= $visitorId;
        $lead->last_seen_at = now();
        if ($convertedUserId !== null && User::whereKey($convertedUserId)->exists()) {
            $lead->converted_user_id = $convertedUserId;
        }
        $lead->save();
    }
}
