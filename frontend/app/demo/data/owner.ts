import type { OwnerAccount, Plan } from "~/types/owner";
import { PLANS } from "~/config/plans";

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
 * Plan ladder — display-only on the settings page. Prices live in
 * `config/plans.ts` (shared with the legal pages); "Upgrade" CTAs toast a
 * Phase-7 stub until billing ships.
 */
export const plansMock: Plan[] = PLANS.map((p) => ({ ...p }));
