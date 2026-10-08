import type { Tenant } from "~/types/tenant";
import type { AuthUser } from "~/types/auth";
import type {
  TenantInviteLink,
  TenantInviteResult,
  TenantProfile,
  TenantsService,
} from "~/services/contracts/tenants";

export const apiTenants: TenantsService = {
  getTenants: () => useApi().request<Tenant[]>("/tenants"),

  getTenant: (id) => useApi().request<Tenant>(`/tenants/${id}`),

  invite: (input) =>
    useApi().request<TenantInviteResult>("/tenants/invite", { method: "POST", body: input }),

  createInviteLink: (id) =>
    useApi().request<TenantInviteLink>(`/tenants/${id}/invite-link`, { method: "POST" }),

  update: (id, patch) =>
    useApi().request<Tenant>(`/tenants/${id}`, { method: "PATCH", body: patch }),

  remove: async (id) => {
    await useApi().request(`/tenants/${id}`, { method: "DELETE" });
  },

  // ── Tenant-shell scope — session-scoped, tenantId is ignored ──────────
  getProfile: () => useApi().request<TenantProfile>("/me/profile"),

  updateProfile: (_tenantId, patch) =>
    useApi().request<TenantProfile>("/me/profile", {
      method: "PATCH",
      body: patch,
    }),

  completeOnboarding: (_tenantId, patch) =>
    useApi().request<AuthUser>("/me/onboarding", { method: "PATCH", body: patch }),
};
