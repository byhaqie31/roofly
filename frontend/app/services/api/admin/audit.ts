import type { AdminAuditService } from "~/services/contracts/admin/audit";
import type { AuditEntry, Paginated } from "~/types/admin";
import { cleanQuery } from "~/services/api/admin/query";

export const apiAdminAudit: AdminAuditService = {
  list: (query) => useApi().request<Paginated<AuditEntry>>("/admin/audit", { query: cleanQuery({ ...query }) }),
  exportCsv: (query) =>
    useApi().request<string>("/admin/audit/export.csv", { query: cleanQuery({ ...query }), responseType: "text" }),
};
