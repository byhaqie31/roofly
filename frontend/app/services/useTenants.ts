import type { TenantsService } from "~/services/contracts/tenants";
import { demoTenants } from "~/demo/services/tenants";
import { apiTenants } from "~/services/api/tenants";

export type {
  TenantProfile,
  TenantProfileUpdate,
} from "~/services/contracts/tenants";

/** Demo → in-memory seed data; otherwise the Laravel API. Chosen once per call. */
export const useTenants = (): TenantsService =>
  useEnv().useMock ? demoTenants : apiTenants;
