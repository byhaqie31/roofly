import type { AdminPermission } from "~/types/admin";

export type UserRole = "owner" | "tenant" | "admin";

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
  /** Admin only — `[]` for owners and tenants. Super-admins get the full list. */
  permissions: AdminPermission[];
  isSuperAdmin: boolean;
}
