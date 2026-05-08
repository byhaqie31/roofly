export type TenantStatus = "invited" | "active" | "moved_out";

export interface Tenant {
  id: string;
  name: string;
  email: string;
  phone: string;
  status: TenantStatus;
  invitedAt: string;
  createdAt: string;
}

export type TenantInput = Pick<Tenant, "name" | "email" | "phone">;

export type TenantUpdate = Partial<
  Pick<Tenant, "name" | "email" | "phone" | "status">
>;
