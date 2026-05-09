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
 * Until then, login/register are mocked + the user is persisted to
 * localStorage so refreshes don't drop the session. When Sanctum lands,
 * `restoreSession` will swap to a `/auth/me` call.
 */
const STORAGE_KEY = "roofly_auth";

const persist = (user: AuthUser | null) => {
  if (!import.meta.client) return;
  try {
    if (user) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(user));
    } else {
      localStorage.removeItem(STORAGE_KEY);
    }
  } catch {
    // Quota / private-mode etc — non-fatal in mock-mode.
  }
};

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
      persist(this.user);
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
      persist(this.user);
      this.loading = false;
    },

    async logout() {
      this.user = null;
      persist(null);
    },

    /**
     * Hydrate auth state from localStorage on client boot. Run once via
     * the `.client` plugin so SSR is unaffected (server has no localStorage).
     */
    restoreSession() {
      if (!import.meta.client) return;
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return;
      try {
        this.user = JSON.parse(raw) as AuthUser;
      } catch {
        localStorage.removeItem(STORAGE_KEY);
      }
    },

    async fetchMe() {
      // Real impl will call /api/auth/me when backend exists.
    },
  },
});
