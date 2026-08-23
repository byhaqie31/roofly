import type {
  AdminOwner, AdminPropertySummary, AdminTenant, AuditEntry, OwnerListQuery, Paginated, WarnOwnerInput,
} from "~/types/admin";

export interface AdminOwnersService {
  list(query: OwnerListQuery): Promise<Paginated<AdminOwner>>;
  get(id: string): Promise<AdminOwner | null>;
  properties(id: string): Promise<AdminPropertySummary[]>;
  tenants(id: string): Promise<AdminTenant[]>;
  history(id: string): Promise<AuditEntry[]>;
  warn(id: string, input: WarnOwnerInput): Promise<void>;
  suspend(id: string, reason: string): Promise<AdminOwner>;
  unsuspend(id: string): Promise<AdminOwner>;
}
