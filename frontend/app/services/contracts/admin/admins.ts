import type { AdminUser, CreateAdminInput, PermissionCatalogue, UpdateAdminInput } from "~/types/admin";

export interface AdminAdminsService {
  permissions(): Promise<PermissionCatalogue>;
  list(): Promise<AdminUser[]>;
  create(input: CreateAdminInput): Promise<AdminUser>;
  update(id: string, patch: UpdateAdminInput): Promise<AdminUser>;
  resendInvite(id: string): Promise<void>;
}
