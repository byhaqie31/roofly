import type { AdminAuditService } from "~/services/contracts/admin/audit";
import { demoAdminAudit } from "~/demo/services/admin/audit";
import { apiAdminAudit } from "~/services/api/admin/audit";

export const useAdminAudit = (): AdminAuditService =>
  useEnv().useMock ? demoAdminAudit : apiAdminAudit;
