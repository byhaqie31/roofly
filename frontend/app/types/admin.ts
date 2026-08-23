// frontend/app/types/admin.ts  (Task 16 appends the entity types below this)
/** Mirrors backend App\Support\AdminPermissions::ALL — same keys, same order. */
export const ADMIN_PERMISSIONS = [
  "dashboard.view",
  "owners.view",
  "tenants.view",
  "owners.warn",
  "owners.suspend",
  "owners.plan",
  "support.manage",
  "broadcast.send",
  "settings.channels",
  "settings.flags",
  "admins.manage",
  "audit.view",
  "users.delete",
] as const;

export type AdminPermission = (typeof ADMIN_PERMISSIONS)[number];

// ── Shared ───────────────────────────────────────────────────────────────
export interface Paginated<T> {
  data: T[];
  meta: { page: number; perPage: number; total: number; lastPage: number };
}

export type PlanTier = "free" | "starter" | "pro" | "business";
export type OwnerStatus = "active" | "suspended";
export type TenantStatus = "invited" | "active" | "notice_given" | "moved_out";

// ── Owner (spec § 6 tier — summary only, never money) ──────────────────
export interface AdminOwnerCounts {
  properties: number;
  units: number;
  unitsOccupied: number;
  tenants: number;
  agreementsActive: number;
  agreementsExpiring30d: number;
  invoicesOverdue: number;
  ticketsOpen: number;
}

export interface AdminOwner {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  businessName: string | null;
  planTier: PlanTier;
  unitsUsed: number;
  unitsCap: number | null; // null = unlimited
  status: OwnerStatus;
  suspendedAt: string | null;
  suspensionReason: string | null;
  createdAt: string;
  lastActiveAt: string | null;
  counts: AdminOwnerCounts;
}

export interface AdminPropertySummary {
  id: string;
  name: string;
  address: { line: string | null; postcode: string | null; city: string | null; state: string | null };
  type: "condo" | "landed" | "shoplot" | "room" | null;
  unitsTotal: number;
  unitsOccupied: number;
  createdAt: string;
}

export interface AdminTenant {
  id: string;
  name: string;
  email: string;
  phone: string | null;
  status: TenantStatus;
  ownerId: string | null;
  ownerName: string | null;
  propertyName: string | null;
  unitLabel: string | null;
  invitedAt: string | null;
  acceptedAt: string | null;
  createdAt: string;
}

// ── Admin users ──────────────────────────────────────────────────────────
export type AdminUserStatus = "invited" | "active" | "disabled";

export interface AdminUser {
  id: string;
  name: string;
  email: string;
  permissions: AdminPermission[];
  isSuperAdmin: boolean;
  status: AdminUserStatus;
  lastActiveAt: string | null;
  createdAt: string;
}

export interface PermissionCatalogue {
  permissions: { key: AdminPermission; preset: boolean }[];
  preset: AdminPermission[];
}

export interface CreateAdminInput {
  name: string;
  email: string;
  permissions: AdminPermission[];
  isSuperAdmin?: boolean;
}

export interface UpdateAdminInput {
  permissions?: AdminPermission[];
  isSuperAdmin?: boolean;
  disabled?: boolean;
}

// ── Audit ────────────────────────────────────────────────────────────────
export type AuditAction =
  | "admin.login"
  | "admin.invite_sent"
  | "admin.invite_accepted"
  | "admin.permissions_changed"
  | "admin.disabled"
  | "admin.enabled"
  | "owner.warned"
  | "owner.suspended"
  | "owner.unsuspended"
  | "tenant.invite_resent"
  | "owner.signup"; // synthesised in owner history only

export const AUDIT_ACTIONS: AuditAction[] = [
  "admin.login", "admin.invite_sent", "admin.invite_accepted", "admin.permissions_changed",
  "admin.disabled", "admin.enabled", "owner.warned", "owner.suspended", "owner.unsuspended",
  "tenant.invite_resent",
];

export interface AuditEntry {
  id: string;
  action: AuditAction;
  actorId: string | null;
  actorName: string | null;
  subjectType: "user" | null;
  subjectId: string | null;
  subjectName: string | null;
  before: Record<string, unknown>;
  after: Record<string, unknown>;
  reason: string | null;
  ip: string | null;
  createdAt: string;
}

// ── Queries / inputs ─────────────────────────────────────────────────────
export interface OwnerListQuery {
  q?: string;
  plan?: PlanTier;
  status?: OwnerStatus;
  overCap?: boolean;
  overdue?: boolean;
  page?: number;
  perPage?: number;
}

export interface TenantListQuery {
  q?: string;
  status?: TenantStatus;
  ownerId?: string;
  page?: number;
  perPage?: number;
}

export interface AuditQuery {
  actorId?: string;
  action?: AuditAction;
  subjectType?: "user";
  subjectId?: string;
  from?: string; // YYYY-MM-DD
  to?: string;
  page?: number;
  perPage?: number;
}

export type WarnTemplate = "payment_overdue";

export interface WarnOwnerInput {
  template: WarnTemplate;
  suspendOn: string; // YYYY-MM-DD
  extraLine?: string;
}

// ── Dashboard (spec § 7) ─────────────────────────────────────────────────
export type AdminAttentionKind =
  | "over_cap"
  | "overdue_3plus"
  | "invite_stale_7d"
  | "no_property_7d"
  | "suspended";

export interface AdminAttentionItem {
  kind: AdminAttentionKind;
  ownerId: string;
  ownerName: string;
  meta: string;
  link: string;
}

export interface AdminDashboardData {
  tiles: {
    owners: { total: number; active: number; suspended: number };
    tenants: { total: number; invitedPending: number };
    properties: number;
    units: { total: number; occupiedPct: number };
    agreementsActive: number;
    agreementsExpiring30d: number;
    supportOpen: number;
  };
  series: {
    months: string[]; // 12 × YYYY-MM, oldest first
    ownerSignups: number[];
    invoicesIssued: number[];
    invoicesPaid: number[];
    inviteAcceptanceRate: number[]; // 0–100
  };
  attention: AdminAttentionItem[];
}
