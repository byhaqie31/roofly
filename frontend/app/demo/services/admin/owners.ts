import type { AdminOwnersService } from "~/services/contracts/admin/owners";
import type { AdminOwner, AuditEntry } from "~/types/admin";
import { adminOwnersMock, adminPropertiesMock, adminTenantsMock, auditMock, pushAudit } from "~/demo/data/admin";
import { paginate } from "~/demo/services/admin/paginate";
import { warningText } from "~/utils/warningText";

const actorId = () => useAuthStore().user?.id ?? null;

export const demoAdminOwners: AdminOwnersService = {
  async list(query) {
    let rows = [...adminOwnersMock].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
    const q = query.q?.trim().toLowerCase();
    if (q) rows = rows.filter((o) => [o.name, o.email, o.businessName ?? ""].some((s) => s.toLowerCase().includes(q)));
    if (query.plan) rows = rows.filter((o) => o.planTier === query.plan);
    if (query.status) rows = rows.filter((o) => o.status === query.status);
    if (query.overCap) rows = rows.filter((o) => o.unitsCap !== null && o.unitsUsed > o.unitsCap);
    if (query.overdue) rows = rows.filter((o) => o.counts.invoicesOverdue > 0);
    return structuredClone(paginate(rows, query.page, query.perPage));
  },

  async get(id) {
    const found = adminOwnersMock.find((o) => o.id === id);
    return found ? structuredClone(found) : null;
  },

  async properties(id) {
    return structuredClone(adminPropertiesMock[id] ?? []);
  },

  async tenants(id) {
    return structuredClone(adminTenantsMock.filter((t) => t.ownerId === id));
  },

  async history(id) {
    const owner = adminOwnersMock.find((o) => o.id === id);
    if (!owner) return [];
    const rows: AuditEntry[] = auditMock.filter((a) => a.subjectId === id);
    rows.push({
      id: `signup-${id}`, action: "owner.signup", actorId: null, actorName: null, subjectType: "user",
      subjectId: id, subjectName: owner.name, before: {}, after: { planTier: owner.planTier },
      reason: null, ip: null, createdAt: owner.createdAt,
    });
    return structuredClone(rows.sort((a, b) => b.createdAt.localeCompare(a.createdAt)));
  },

  async warn(id, input) {
    const owner = adminOwnersMock.find((o) => o.id === id);
    if (!owner) throw new Error(`Owner ${id} not found`);
    const text = warningText(input.template, input.suspendOn, input.extraLine ?? null);
    pushAudit({ action: "owner.warned", actorId: actorId(), subjectType: "user", subjectId: id, before: {}, after: { ...input, extraLine: input.extraLine ?? null, text }, reason: null });
  },

  async suspend(id, reason) {
    const owner = adminOwnersMock.find((o) => o.id === id);
    if (!owner) throw new Error(`Owner ${id} not found`);
    if (owner.status === "suspended") throw new Error("Owner is already suspended.");
    owner.status = "suspended";
    owner.suspendedAt = new Date().toISOString();
    owner.suspensionReason = reason;
    pushAudit({ action: "owner.suspended", actorId: actorId(), subjectType: "user", subjectId: id, before: { status: "active" }, after: { status: "suspended" }, reason });
    return structuredClone(owner);
  },

  async unsuspend(id) {
    const owner = adminOwnersMock.find((o) => o.id === id);
    if (!owner) throw new Error(`Owner ${id} not found`);
    if (owner.status !== "suspended") throw new Error("Owner is not suspended.");
    const before = { status: "suspended", suspensionReason: owner.suspensionReason };
    owner.status = "active";
    owner.suspendedAt = null;
    owner.suspensionReason = null;
    pushAudit({ action: "owner.unsuspended", actorId: actorId(), subjectType: "user", subjectId: id, before, after: { status: "active" }, reason: null });
    return structuredClone(owner as AdminOwner);
  },
};
