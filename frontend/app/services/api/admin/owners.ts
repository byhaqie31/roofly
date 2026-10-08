import type { AdminOwnersService } from "~/services/contracts/admin/owners";
import type { AdminOwner, AdminPropertySummary, AdminTenant, AuditEntry, Paginated } from "~/types/admin";
import { cleanQuery } from "~/services/api/admin/query";

export const apiAdminOwners: AdminOwnersService = {
  list: (query) => useApi().request<Paginated<AdminOwner>>("/admin/owners", { query: cleanQuery({ ...query }) }),
  get: async (id) => {
    try {
      return await useApi().request<AdminOwner>(`/admin/owners/${id}`);
    } catch (e) {
      if ((e as { statusCode?: number })?.statusCode === 404) return null;
      throw e;
    }
  },
  properties: (id) => useApi().request<AdminPropertySummary[]>(`/admin/owners/${id}/properties`),
  tenants: (id) => useApi().request<AdminTenant[]>(`/admin/owners/${id}/tenants`),
  history: (id) => useApi().request<AuditEntry[]>(`/admin/owners/${id}/history`),
  warn: async (id, input) => {
    await useApi().request(`/admin/owners/${id}/warn`, { method: "POST", body: input });
  },
  suspend: (id, reason) => useApi().request<AdminOwner>(`/admin/owners/${id}/suspend`, { method: "POST", body: { reason } }),
  unsuspend: (id) => useApi().request<AdminOwner>(`/admin/owners/${id}/unsuspend`, { method: "POST" }),
};
