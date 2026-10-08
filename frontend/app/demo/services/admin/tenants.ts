import type { AdminTenantsService } from "~/services/contracts/admin/tenants";
import { adminTenantIdByEmail, adminTenantsMock, pushAudit } from "~/demo/data/admin";
import { paginate } from "~/demo/services/admin/paginate";

export const demoAdminTenants: AdminTenantsService = {
  async list(query) {
    let rows = [...adminTenantsMock].sort((a, b) => b.createdAt.localeCompare(a.createdAt));
    const q = query.q?.trim().toLowerCase();
    // Mirrors Admin\TenantController: exact email, or part of a property / owner
    // name — never a partial tenant name, email or phone.
    if (q) {
      const byEmail = adminTenantIdByEmail(q);
      rows = rows.filter((t) => t.id === byEmail || [t.propertyName ?? "", t.ownerName ?? ""].some((s) => s.toLowerCase().includes(q)));
    }
    if (query.status) rows = rows.filter((t) => t.status === query.status);
    if (query.ownerId) rows = rows.filter((t) => t.ownerId === query.ownerId);
    return structuredClone(paginate(rows, query.page, query.perPage));
  },

  async get(id) {
    const found = adminTenantsMock.find((t) => t.id === id);
    return found ? structuredClone(found) : null;
  },

  async resendInvite(id) {
    const t = adminTenantsMock.find((x) => x.id === id);
    if (!t) throw new Error(`Tenant ${id} not found`);
    if (t.status !== "invited") throw new Error("Only pending invites can be resent.");
    const before = { invitedAt: t.invitedAt };
    t.invitedAt = new Date().toISOString();
    pushAudit({ action: "tenant.invite_resent", actorId: useAuthStore().user?.id ?? null, subjectType: "user", subjectId: id, before, after: { invitedAt: t.invitedAt }, reason: null });
  },
};
