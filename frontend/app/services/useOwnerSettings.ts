import type {
  NotificationPreferencesUpdate,
  OwnerAccount,
  OwnerPreferencesUpdate,
  OwnerProfileUpdate,
  Plan,
} from "~/types/owner";
import { ownerAccountMock, plansMock } from "~/mocks/owner";

export const useOwnerSettings = () => {
  const { public: { useMock } } = useRuntimeConfig();

  const get = async (): Promise<OwnerAccount> => {
    if (useMock) return structuredClone(ownerAccountMock);
    const { request } = useApi();
    return request<OwnerAccount>("/account");
  };

  const updateProfile = async (
    patch: OwnerProfileUpdate,
  ): Promise<OwnerAccount> => {
    if (useMock) {
      ownerAccountMock.profile = { ...ownerAccountMock.profile, ...patch };
      return structuredClone(ownerAccountMock);
    }
    const { request } = useApi();
    return request<OwnerAccount>("/account/profile", {
      method: "PATCH",
      body: patch,
    });
  };

  const updatePreferences = async (
    patch: OwnerPreferencesUpdate,
  ): Promise<OwnerAccount> => {
    if (useMock) {
      ownerAccountMock.preferences = {
        ...ownerAccountMock.preferences,
        ...patch,
      };
      return structuredClone(ownerAccountMock);
    }
    const { request } = useApi();
    return request<OwnerAccount>("/account/preferences", {
      method: "PATCH",
      body: patch,
    });
  };

  const updateNotifications = async (
    patch: NotificationPreferencesUpdate,
  ): Promise<OwnerAccount> => {
    if (useMock) {
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
    }
    const { request } = useApi();
    return request<OwnerAccount>("/account/notifications", {
      method: "PATCH",
      body: patch,
    });
  };

  const listPlans = async (): Promise<Plan[]> => {
    if (useMock) return structuredClone(plansMock);
    const { request } = useApi();
    return request<Plan[]>("/plans");
  };

  return {
    get,
    updateProfile,
    updatePreferences,
    updateNotifications,
    listPlans,
  };
};
