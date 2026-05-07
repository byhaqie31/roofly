import { defineStore } from "pinia";

export type UserRole = "owner" | "tenant" | "admin";

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
}

interface AuthState {
  user: AuthUser | null;
  loading: boolean;
}

/**
 * Phase 1 auth store. Backend wiring lands in the Laravel install commit.
 * Until then, login/register are mocked so the frontend shells are
 * navigable end-to-end.
 */
export const useAuthStore = defineStore("auth", {
  state: (): AuthState => ({
    user: null,
    loading: false,
  }),

  getters: {
    isAuthenticated: (s) => s.user !== null,
    isOwner: (s) => s.user?.role === "owner",
    isTenant: (s) => s.user?.role === "tenant",
    isAdmin: (s) => s.user?.role === "admin",
  },

  actions: {
    /** TEMP: mock until backend is up. */
    async login(email: string, _password: string) {
      this.loading = true;
      await new Promise((r) => setTimeout(r, 300));
      const role: UserRole = email.startsWith("tenant")
        ? "tenant"
        : email.startsWith("admin")
          ? "admin"
          : "owner";
      this.user = {
        id: "stub-" + role,
        name: role === "tenant" ? "Adi" : role === "admin" ? "Admin" : "Cik Aminah",
        email,
        phone: null,
        role,
      };
      this.loading = false;
    },

    async register(payload: { name: string; email: string; phone: string; password: string }) {
      this.loading = true;
      await new Promise((r) => setTimeout(r, 300));
      this.user = {
        id: "stub-owner",
        name: payload.name,
        email: payload.email,
        phone: payload.phone,
        role: "owner",
      };
      this.loading = false;
    },

    async logout() {
      this.user = null;
    },

    async fetchMe() {
      // Real impl will call /api/auth/me when backend exists
    },
  },
});
