import type { AdminPermission } from "~/types/admin";

export type UserRole = "owner" | "tenant" | "admin";

export type OwnerPurpose = "rental" | "own_stay" | "investment";
export const OWNER_PURPOSES: readonly OwnerPurpose[] = ["rental", "own_stay", "investment"] as const;

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
  /** Admin only — `[]` for owners and tenants. Super-admins get the full list. */
  permissions: AdminPermission[];
  isSuperAdmin: boolean;
  /** False for Google-only accounts — Settings → Profile offers "Set a password". */
  hasPassword: boolean;
  avatarUrl: string | null;
  /** Owners only. `null` ⇒ the route guard sends them to /owner/onboarding. */
  onboardedAt: string | null;
  /** Owners only — `[]` until onboarded. */
  purposes: OwnerPurpose[];
  checklistDismissedAt: string | null;
}
