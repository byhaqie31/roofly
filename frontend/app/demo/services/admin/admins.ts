import type { AdminAdminsService } from "~/services/contracts/admin/admins";
import type { AdminUser } from "~/types/admin";
import { ADMIN_PERMISSIONS } from "~/types/admin";
import { DEMO_OPS_PRESET } from "~/demo/auth";
import { adminUsersMock, pushAudit } from "~/demo/data/admin";

const me = () => useAuthStore().user?.id ?? null;

export const demoAdminAdmins: AdminAdminsService = {
  async permissions() {
    return { permissions: ADMIN_PERMISSIONS.map((key) => ({ key, preset: DEMO_OPS_PRESET.includes(key) })), preset: [...DEMO_OPS_PRESET] };
  },

  async list() {
    return structuredClone([...adminUsersMock].sort((a, b) => b.createdAt.localeCompare(a.createdAt)));
  },

  async create(input) {
    if (adminUsersMock.some((a) => a.email === input.email)) throw new Error("Email already in use.");
    const created: AdminUser = {
      id: crypto.randomUUID(), name: input.name, email: input.email, permissions: [...input.permissions],
      isSuperAdmin: input.isSuperAdmin ?? false, status: "invited", lastActiveAt: null, createdAt: new Date().toISOString(),
    };
    adminUsersMock.push(created);
    pushAudit({ action: "admin.invite_sent", actorId: me(), subjectType: "user", subjectId: created.id, before: {}, after: { permissions: created.permissions, isSuperAdmin: created.isSuperAdmin }, reason: null });
    return structuredClone(created);
  },

  async update(id, patch) {
    const a = adminUsersMock.find((x) => x.id === id);
    if (!a) throw new Error(`Admin ${id} not found`);
    if (patch.disabled && id === me()) throw new Error("You cannot disable your own account.");
    const wouldDrop = (patch.disabled === true) || (patch.isSuperAdmin === false);
    if (a.isSuperAdmin && a.status !== "disabled" && wouldDrop) {
      const others = adminUsersMock.filter((x) => x.id !== id && x.isSuperAdmin && x.status !== "disabled").length;
      if (others === 0) throw new Error("There must always be at least one enabled super-admin.");
    }
    if (patch.permissions !== undefined || patch.isSuperAdmin !== undefined) {
      const before = { permissions: [...a.permissions], isSuperAdmin: a.isSuperAdmin };
      if (patch.permissions !== undefined) a.permissions = [...patch.permissions];
      if (patch.isSuperAdmin !== undefined) a.isSuperAdmin = patch.isSuperAdmin;
      pushAudit({ action: "admin.permissions_changed", actorId: me(), subjectType: "user", subjectId: id, before, after: { permissions: [...a.permissions], isSuperAdmin: a.isSuperAdmin }, reason: null });
    }
    if (patch.disabled === true && a.status !== "disabled") {
      a.status = "disabled";
      pushAudit({ action: "admin.disabled", actorId: me(), subjectType: "user", subjectId: id, before: { status: "active" }, after: { status: "disabled" }, reason: null });
    } else if (patch.disabled === false && a.status === "disabled") {
      a.status = a.lastActiveAt ? "active" : "invited";
      pushAudit({ action: "admin.enabled", actorId: me(), subjectType: "user", subjectId: id, before: { status: "disabled" }, after: { status: "active" }, reason: null });
    }
    return structuredClone(a);
  },

  async resendInvite(id) {
    const a = adminUsersMock.find((x) => x.id === id);
    if (!a) throw new Error(`Admin ${id} not found`);
    if (a.status !== "invited") throw new Error("This admin has already accepted their invite.");
    pushAudit({ action: "admin.invite_sent", actorId: me(), subjectType: "user", subjectId: id, before: {}, after: { resend: true }, reason: null });
  },
};
