import type { OwnerAccount, Plan } from "~/types/owner";
import type { OwnerSettingsService } from "~/services/contracts/ownerSettings";
import type { AuthUser } from "~/types/auth";

export const apiOwnerSettings: OwnerSettingsService = {
  getAccount: () => useApi().request<OwnerAccount>("/account"),

  updateProfile: (patch) =>
    useApi().request<OwnerAccount>("/account/profile", {
      method: "PATCH",
      body: patch,
    }),

  updatePreferences: (patch) =>
    useApi().request<OwnerAccount>("/account/preferences", {
      method: "PATCH",
      body: patch,
    }),

  updateNotifications: (patch) =>
    useApi().request<OwnerAccount>("/account/notifications", {
      method: "PATCH",
      body: patch,
    }),

  getPlans: () => useApi().request<Plan[]>("/plans"),

  completeOnboarding: (input) =>
    useApi().request<AuthUser>("/account/onboarding", { method: "PATCH", body: input }),

  setChecklistDismissed: (dismissed) =>
    useApi().request<AuthUser>("/account/checklist", { method: "PATCH", body: { dismissed } }),

  setPassword: (password) =>
    useApi().request<AuthUser>("/account/password", {
      method: "POST",
      body: { password, password_confirmation: password },
    }),
};
