import type { AdminAdminsService } from "~/services/contracts/admin/admins";
import type { AdminUser, PermissionCatalogue } from "~/types/admin";

export const apiAdminAdmins: AdminAdminsService = {
  permissions: () => useApi().request<PermissionCatalogue>("/admin/permissions"),
  list: () => useApi().request<AdminUser[]>("/admin/admins"),
  create: (input) => useApi().request<AdminUser>("/admin/admins", { method: "POST", body: input }),
  update: (id, patch) => useApi().request<AdminUser>(`/admin/admins/${id}`, { method: "PATCH", body: patch }),
  resendInvite: async (id) => {
    await useApi().request(`/admin/admins/${id}/resend-invite`, { method: "POST" });
  },
};
