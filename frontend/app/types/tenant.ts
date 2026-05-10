export type TenantStatus =
  | "invited"        // sent invite, hasn't accepted
  | "active"         // signed agreement, currently occupying
  | "notice_given"   // tenant gave notice — owners want the 30-day vacancy heads-up
  | "moved_out";     // gone, agreement closed

export interface TenantPersonal {
  icNumber?: string;          // MyKad e.g. "880314-14-5687"
  dateOfBirth?: string;       // ISO date
  occupation?: string;
  employer?: string;
  monthlyIncome?: number;     // sen
  nationality?: string;
  photoUrl?: string;          // Phase 4+ — needs storage
}

export interface TenantEmergencyContact {
  name?: string;
  phone?: string;
  relationship?: string;
}

export interface Tenant {
  id: string;
  name: string;
  email: string;
  phone: string;
  status: TenantStatus;
  invitedAt: string;
  createdAt: string;
  personal?: TenantPersonal;
  emergencyContact?: TenantEmergencyContact;
}

export type TenantInput = Pick<Tenant, "name" | "email" | "phone">;

export type TenantUpdate = Partial<
  Pick<Tenant, "name" | "email" | "phone" | "status">
> & {
  personal?: TenantPersonal;
  emergencyContact?: TenantEmergencyContact;
};
