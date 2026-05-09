/**
 * Owner settings model. Sits alongside `AuthUser` (which carries the
 * minimal auth-shaped identity) — this file holds the full profile +
 * per-owner preferences edited from the settings page.
 */

export type Locale = "en" | "ms";
export type ThemePreference = "light" | "dark" | "system";

export interface OwnerProfile {
  id: string;                       // matches AuthUser.id
  name: string;
  email: string;                    // managed by auth — not editable from settings
  phone: string;
  photoUrl?: string;                // Phase 4+ (file storage)
  businessName?: string;            // optional — landlord operating as a sole prop
  bankAccountLast4?: string;        // shown read-only; full account managed elsewhere
}

export interface OwnerPreferences {
  locale: Locale;
  theme: ThemePreference;
  // Money locale is fixed to en-MY across the app per useMoney; surface here
  // for forward-compat once we add ringgit/cents toggle or other locales.
  moneyLocale: "en-MY";
}

export type NotificationEvent =
  | "rent_reminder"          // 7d/3d/1d before rent due
  | "agreement_expiry"       // 60d/30d/7d before agreement endDate
  | "payment_received"       // tenant paid an invoice
  | "ticket_update"          // tenant added a comment or changed status
  | "invite_accepted";       // tenant accepted the magic-link invite

export type NotificationChannel = "email" | "whatsapp" | "in_app";

export interface NotificationPreferences {
  events: Record<NotificationEvent, boolean>;
  // Channels are display-only until Phase 4 (Resend + WhatsApp Cloud API).
  // The shape is here so the swap is just a flip.
  channels: Record<NotificationChannel, boolean>;
}

export type PlanTier = "free" | "starter" | "pro" | "business";

export interface Plan {
  tier: PlanTier;
  priceRm: number;                  // monthly RM
  unitsCap: number | "unlimited";
  description: string;              // i18n key fragment under owner.settings.plan.tiers
}

export interface OwnerAccount {
  profile: OwnerProfile;
  preferences: OwnerPreferences;
  notifications: NotificationPreferences;
  planTier: PlanTier;
}

export type OwnerProfileUpdate = Partial<
  Pick<OwnerProfile, "name" | "phone" | "businessName">
>;
export type OwnerPreferencesUpdate = Partial<OwnerPreferences>;
export type NotificationPreferencesUpdate = Partial<NotificationPreferences>;
