import type { Plan } from "~/types/owner";

/**
 * Plan ladder (PROJECT.md § 12). Read by the demo settings adapter and by the
 * Terms + Billing legal pages, so prices live in one place on the frontend.
 * The backend mirrors it in `Owner\AccountController::plans()` — change both.
 */
export const PLANS: readonly Plan[] = [
  { tier: "free", priceRm: 0, unitsCap: 2, description: "free" }, // cap matches PlanCaps + PROJECT.md § 12
  { tier: "starter", priceRm: 49, unitsCap: 5, description: "starter" },
  { tier: "pro", priceRm: 99, unitsCap: 25, description: "pro" },
  { tier: "business", priceRm: 199, unitsCap: "unlimited", description: "business" },
];
