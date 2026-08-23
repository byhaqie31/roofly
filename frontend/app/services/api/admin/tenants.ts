import type { AdminTenantsService } from "~/services/contracts/admin/tenants";
import type { AdminTenant, Paginated } from "~/types/admin";
import { cleanQuery } from "~/services/api/admin/query";

export const apiAdminTenants: AdminTenantsService = {
  list: (query) => useApi().request<Paginated<AdminTenant>>("/admin/tenants", { query: cleanQuery({ ...query }) }),
  get: async (id) => {
    try {
      return await useApi().request<AdminTenant>(`/admin/tenants/${id}`);
    } catch (e) {
      if ((e as { statusCode?: number })?.statusCode === 404) return null;
      throw e;
    }
  },
  resendInvite: async (id) => {
    await useApi().request(`/admin/tenants/${id}/resend-invite`, { method: "POST" });
  },
};
