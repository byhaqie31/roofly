import type { AdminTenant, Paginated, TenantListQuery } from "~/types/admin";

export interface AdminTenantsService {
  list(query: TenantListQuery): Promise<Paginated<AdminTenant>>;
  get(id: string): Promise<AdminTenant | null>;
  resendInvite(id: string): Promise<void>;
}
