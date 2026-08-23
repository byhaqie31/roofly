import type { AdminOwnersService } from "~/services/contracts/admin/owners";
import { demoAdminOwners } from "~/demo/services/admin/owners";
import { apiAdminOwners } from "~/services/api/admin/owners";

export const useAdminOwners = (): AdminOwnersService =>
  useEnv().useMock ? demoAdminOwners : apiAdminOwners;
