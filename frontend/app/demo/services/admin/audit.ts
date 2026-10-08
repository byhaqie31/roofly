import type { AdminAuditService } from "~/services/contracts/admin/audit";
import type { AuditEntry, AuditQuery } from "~/types/admin";
import { auditMock } from "~/demo/data/admin";
import { paginate } from "~/demo/services/admin/paginate";
import { buildCsv } from "~/utils/csv";

const filter = (query: AuditQuery): AuditEntry[] => {
  const user = useAuthStore().user;
  const seesAll = !!user && (user.isSuperAdmin || user.permissions.includes("audit.view"));
  let rows = [...auditMock].sort((a, b) => b.createdAt.localeCompare(a.createdAt) || b.id.localeCompare(a.id));
  if (!seesAll) rows = rows.filter((r) => r.actorId === user?.id);
  if (query.actorId) rows = rows.filter((r) => r.actorId === query.actorId);
  if (query.action) rows = rows.filter((r) => r.action === query.action);
  if (query.subjectType) rows = rows.filter((r) => r.subjectType === query.subjectType);
  if (query.subjectId) rows = rows.filter((r) => r.subjectId === query.subjectId);
  if (query.from) rows = rows.filter((r) => r.createdAt >= `${query.from}T00:00:00`);
  if (query.to) rows = rows.filter((r) => r.createdAt <= `${query.to}T23:59:59.999Z`);
  return rows;
};

export const demoAdminAudit: AdminAuditService = {
  async list(query) {
    const rows = filter(query);
    return structuredClone(paginate(rows, query.page, query.perPage ?? 25));
  },

  async exportCsv(query) {
    return buildCsv(
      ["id", "createdAt", "action", "actorName", "subjectType", "subjectId", "subjectName", "reason", "before", "after"],
      filter(query).map((r) => [r.id, r.createdAt, r.action, r.actorName, r.subjectType, r.subjectId, r.subjectName, r.reason, JSON.stringify(r.before), JSON.stringify(r.after)]),
    );
  },
};
