import type { AuthUser } from "~/types/auth";
import type { AuthAdapter } from "~/services/contracts/auth";

/**
 * Sanctum SPA cookie auth. The httpOnly session cookie is the only
 * persistence — `login`/`register` prime the CSRF cookie then POST;
 * `fetchMe` hydrates on boot.
 */
export const apiAuth: AuthAdapter = {
  async login(email, password) {
    const { request } = useApi();
    // Prime the CSRF cookie before the stateful POST.
    await request("/../sanctum/csrf-cookie");
    const res = await request<{ user: AuthUser }>("/auth/login", {
      method: "POST",
      body: { email, password },
    });
    return res.user;
  },

  async register(payload) {
    const { request } = useApi();
    await request("/../sanctum/csrf-cookie");
    const res = await request<{ user: AuthUser }>("/auth/register", {
      method: "POST",
      body: { ...payload, password_confirmation: payload.password },
    });
    return res.user;
  },

  async logout() {
    try {
      await useApi().request("/auth/logout", { method: "POST" });
    } catch {
      // Even if the server call fails, the store drops local state.
    }
  },

  async fetchMe() {
    try {
      return await useApi().request<AuthUser>("/auth/me");
    } catch {
      // 401 is the expected "not logged in" case, not an error to surface.
      return null;
    }
  },

  async loginAdmin(email, password) {
    const { request } = useApi();
    await request("/../sanctum/csrf-cookie");
    const res = await request<{ user: AuthUser }>("/admin/auth/login", {
      method: "POST",
      body: { email, password },
    });
    return res.user;
  },

  async acceptAdminInvite(token, password) {
    const { request } = useApi();
    await request("/../sanctum/csrf-cookie");
    const res = await request<{ user: AuthUser }>("/admin/auth/accept-invite", {
      method: "POST",
      body: { token, password, password_confirmation: password },
    });
    return res.user;
  },
};
