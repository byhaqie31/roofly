import type {
  NotificationPreferencesUpdate,
  OwnerAccount,
  OwnerPreferencesUpdate,
  OwnerProfileUpdate,
  Plan,
} from "~/types/owner";
import type { AuthUser, OwnerPurpose } from "~/types/auth";

export interface OwnerSettingsService {
  getAccount(): Promise<OwnerAccount>;
  updateProfile(patch: OwnerProfileUpdate): Promise<OwnerAccount>;
  updatePreferences(patch: OwnerPreferencesUpdate): Promise<OwnerAccount>;
  updateNotifications(
    patch: NotificationPreferencesUpdate,
  ): Promise<OwnerAccount>;
  getPlans(): Promise<Plan[]>;
  /** Onboarding answer; idempotent — re-calling updates purposes only. Returns the refreshed AuthUser. */
  completeOnboarding(input: { purposes: OwnerPurpose[] }): Promise<AuthUser>;
  setChecklistDismissed(dismissed: boolean): Promise<AuthUser>;
  /** Only for accounts with `hasPassword === false`. */
  setPassword(password: string): Promise<AuthUser>;
}
