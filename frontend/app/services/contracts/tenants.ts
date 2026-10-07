import type {
  Tenant,
  TenantEmergencyContact,
  TenantInput,
  TenantPersonal,
  TenantUpdate,
} from "~/types/tenant";

/** What a tenant sees of themselves — no owner-side fields (status, invitedAt). */
export type TenantProfile = Pick<
  Tenant,
  "id" | "name" | "email" | "phone" | "personal" | "emergencyContact"
>;

/** Email is the login identity and is not editable from the profile. */
export interface TenantProfileUpdate {
  name?: string;
  phone?: string;
  personal?: TenantPersonal;
  emergencyContact?: TenantEmergencyContact;
}

/** A plain invite link exists only at the moment it's issued (only its hash is stored). */
export interface TenantInviteLink {
  inviteUrl: string;
  inviteExpiresAt: string; // ISO datetime, 7 days out
}

export interface TenantInviteResult extends TenantInviteLink {
  tenant: Tenant;
}

export interface TenantsService {
  getTenants(): Promise<Tenant[]>;
  getTenant(id: string): Promise<Tenant | null>;
  /** Creates the tenant, emails the set-password link, and returns that same link once. */
  invite(input: TenantInput): Promise<TenantInviteResult>;
  /**
   * Owner backup for a lost email: mints a FRESH link (earlier ones stop
   * working) without sending mail. Rejects unless the tenant is still `invited`.
   */
  createInviteLink(id: string): Promise<TenantInviteLink>;
  update(id: string, patch: TenantUpdate): Promise<Tenant>;
  remove(id: string): Promise<void>;

  // ── Tenant-shell scope (`/me/profile`) ────────────────────────────────
  /** The signed-in tenant's own profile. `tenantId` is only used by demo. */
  getProfile(tenantId: string): Promise<TenantProfile | null>;
  updateProfile(
    tenantId: string,
    patch: TenantProfileUpdate,
  ): Promise<TenantProfile>;
}
