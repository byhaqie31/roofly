import type { TenantProfile } from "~/services/contracts/tenants";

/**
 * The profile details a landlord needs before an agreement (spec 2026-10-07
 * § 4.4). Pure — the tenant home's "Complete your profile" card and the
 * onboarding guard both derive state from the live profile, never from a
 * stored flag, so the nudge disappears the moment the data exists.
 */
export const TENANT_CORE_FIELDS = ["phone", "icNumber", "ecName", "ecPhone"] as const;
export type TenantCoreField = (typeof TENANT_CORE_FIELDS)[number];

export interface TenantProfileGaps {
  missing: TenantCoreField[];
  done: number;
  total: number;
}

const filled = (value: string | undefined | null): boolean => (value ?? "").trim() !== "";

export const tenantProfileGaps = (profile: TenantProfile | null): TenantProfileGaps => {
  const present: Record<TenantCoreField, boolean> = {
    phone: filled(profile?.phone),
    icNumber: filled(profile?.personal?.icNumber),
    ecName: filled(profile?.emergencyContact?.name),
    ecPhone: filled(profile?.emergencyContact?.phone),
  };
  const missing = TENANT_CORE_FIELDS.filter((f) => !present[f]);
  return { missing, done: TENANT_CORE_FIELDS.length - missing.length, total: TENANT_CORE_FIELDS.length };
};
