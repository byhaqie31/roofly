import type { OwnerAccount, Plan } from "~/types/owner";

/**
 * Single owner record. The `id` matches the auth-store stub user
 * ("stub-owner") so the settings page hydrates against the logged-in user.
 */
export const ownerAccountMock: OwnerAccount = {
  profile: {
    id: "stub-owner",
    name: "Cik Aminah",
    email: "aminah@roofly.my",
    phone: "+60 12-345 6789",
    businessName: "Aminah Properties",
    bankAccountLast4: "4521",
  },
  preferences: {
    locale: "en",
    theme: "system",
    moneyLocale: "en-MY",
  },
  notifications: {
    events: {
      rent_reminder: true,
      agreement_expiry: true,
      payment_received: true,
      ticket_update: true,
      invite_accepted: true,
    },
    channels: {
      email: true,
      whatsapp: false,    // Phase 4 — defaults off until WhatsApp Cloud API is wired
      in_app: true,
    },
  },
  planTier: "free",
};

/**
 * Plan ladder — display-only on the settings page. Pricing matches PROJECT.md
 * § 12; "Upgrade" CTAs toast a Phase-7 stub until billing ships.
 */
export const plansMock: Plan[] = [
  { tier: "free", priceRm: 0, unitsCap: 3, description: "free" },
  { tier: "starter", priceRm: 29, unitsCap: 5, description: "starter" },
  { tier: "pro", priceRm: 79, unitsCap: 25, description: "pro" },
  { tier: "business", priceRm: 199, unitsCap: "unlimited", description: "business" },
];
