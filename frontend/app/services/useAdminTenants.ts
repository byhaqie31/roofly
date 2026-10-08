import type { AdminTenantsService } from "~/services/contracts/admin/tenants";
import { demoAdminTenants } from "~/demo/services/admin/tenants";
import { apiAdminTenants } from "~/services/api/admin/tenants";

export const useAdminTenants = (): AdminTenantsService =>
  useEnv().useMock ? demoAdminTenants : apiAdminTenants;
