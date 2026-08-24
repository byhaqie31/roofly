import { defineStore } from "pinia";
import type { AuthUser } from "~/types/auth";
import type { AuthAdapter, RegisterPayload } from "~/services/contracts/auth";
import { demoAuth } from "~/demo/auth";
import { apiAuth } from "~/services/api/auth";

export type { AuthUser, UserRole } from "~/types/auth";

interface AuthState {
  user: AuthUser | null;
  loading: boolean;
  /** False until the boot `fetchMe()` has settled — the route guard waits on this. */
  authReady: boolean;
}

/** Demo → localStorage-backed stub; otherwise Sanctum SPA cookie auth. */
const adapter = (): AuthAdapter => (useEnv().useMock ? demoAuth : apiAuth);

export const useAuthStore = defineStore("auth", {
  state: (): AuthState => ({
    user: null,
    loading: false,
    authReady: false,
  }),

  getters: {
    isAuthenticated: (s) => s.user !== null,
    isOwner: (s) => s.user?.role === "owner",
    isTenant: (s) => s.user?.role === "tenant",
    isAdmin: (s) => s.user?.role === "admin",
  },

  actions: {
    async login(email: string, password: string) {
      this.loading = true;
      try {
        this.user = await adapter().login(email, password);
      } finally {
        this.loading = false;
      }
    },

    async register(payload: RegisterPayload) {
      this.loading = true;
      try {
        this.user = await adapter().register(payload);
      } finally {
        this.loading = false;
      }
    },

    async loginWithGoogle(credential: string) {
      this.loading = true;
      try {
        this.user = await adapter().loginWithGoogle(credential);
      } finally {
        this.loading = false;
      }
    },

    /** Replace the session user after an account mutation (onboarding, checklist, password). */
    setUser(user: AuthUser) {
      this.user = user;
    },

    async loginAdmin(email: string, password: string) {
      this.loading = true;
      try {
        this.user = await adapter().loginAdmin(email, password);
      } finally {
        this.loading = false;
      }
    },

    async acceptAdminInvite(token: string, password: string) {
      this.loading = true;
      try {
        this.user = await adapter().acceptAdminInvite(token, password);
      } finally {
        this.loading = false;
      }
    },

    async forgotPassword(email: string) {
      this.loading = true;
      try {
        await adapter().forgotPassword(email);
      } finally {
        this.loading = false;
      }
    },

    async resetPassword(input: { token: string; email: string; password: string }) {
      this.loading = true;
      try {
        this.user = await adapter().resetPassword(input);
      } finally {
        this.loading = false;
      }
    },

    async logout() {
      await adapter().logout();
      this.user = null;
    },

    /**
     * Boot hydration. Always marks the session ready so the route guard can
     * proceed; a signed-out result is `user = null`, not an error.
     */
    async fetchMe() {
      try {
        this.user = await adapter().fetchMe();
      } catch {
        this.user = null;
      } finally {
        this.authReady = true;
      }
    },
  },
});
