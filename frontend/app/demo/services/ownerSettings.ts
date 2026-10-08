import type { OwnerSettingsService } from "~/services/contracts/ownerSettings";
import { ownerAccountMock, plansMock } from "~/demo/data/owner";
import { demoSession } from "~/demo/auth";

export const demoOwnerSettings: OwnerSettingsService = {
  async getAccount() {
    return structuredClone(ownerAccountMock);
  },

  async updateProfile(patch) {
    ownerAccountMock.profile = { ...ownerAccountMock.profile, ...patch };
    return structuredClone(ownerAccountMock);
  },

  async updatePreferences(patch) {
    ownerAccountMock.preferences = { ...ownerAccountMock.preferences, ...patch };
    return structuredClone(ownerAccountMock);
  },

  async updateNotifications(patch) {
    ownerAccountMock.notifications = {
      events: {
        ...ownerAccountMock.notifications.events,
        ...(patch.events ?? {}),
      },
      channels: {
        ...ownerAccountMock.notifications.channels,
        ...(patch.channels ?? {}),
      },
    };
    return structuredClone(ownerAccountMock);
  },

  async getPlans() {
    return structuredClone(plansMock);
  },

  async completeOnboarding({ purposes }) {
    const current = demoSession.current();
    return demoSession.update({
      purposes: [...new Set(purposes)],
      onboardedAt: current?.onboardedAt ?? new Date().toISOString(),
    });
  },

  async setChecklistDismissed(dismissed) {
    return demoSession.update({
      checklistDismissedAt: dismissed ? new Date().toISOString() : null,
    });
  },

  async setPassword() {
    return demoSession.update({ hasPassword: true });
  },
};
