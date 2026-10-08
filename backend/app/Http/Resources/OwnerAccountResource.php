<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** Frontend OwnerAccount envelope — see frontend/app/types/owner.ts. */
class OwnerAccountResource extends JsonResource
{
    public static function defaultPreferences(): array
    {
        return ['locale' => 'en', 'theme' => 'system', 'moneyLocale' => 'en-MY'];
    }

    public static function defaultNotifications(): array
    {
        return [
            'events'   => ['rent_reminder' => true, 'agreement_expiry' => true, 'payment_received' => true, 'ticket_update' => true, 'invite_accepted' => true],
            'channels' => ['email' => true, 'whatsapp' => false, 'in_app' => true],
        ];
    }

    public function toArray($request): array
    {
        return [
            'profile' => [
                'id'               => $this->id,
                'name'             => $this->name,
                'email'            => $this->email,
                'phone'            => $this->phone,
                'photoUrl'         => null, // Phase 4 — file storage
                'businessName'     => $this->business_name,
            ],
            'preferences' => $this->owner_preferences ?? self::defaultPreferences(),
            'notifications' => $this->notification_preferences ?? self::defaultNotifications(),
            'planTier' => $this->plan_tier?->value,
        ];
    }
}
