import type { Tenant } from "~/types/tenant";
import type {
  TenantInviteLink,
  TenantProfile,
  TenantsService,
} from "~/services/contracts/tenants";
import { tenantsMock } from "~/demo/data/tenants";

const DAY_MS = 24 * 60 * 60 * 1000;

// Demo stand-in for the emailed set-password link. There's no mail transport
// in demo, so the owner's copy / WhatsApp backup is the only way a demo tenant
// would ever "receive" it. The token is decorative — demoAuth.acceptInvite
// ignores it and just signs in the fixed tenant record.
const demoInviteLink = (email: string): TenantInviteLink => {
  const origin = typeof window !== "undefined" ? window.location.origin : "https://demo.roofly.my";
  const token = `demo-${crypto.randomUUID().slice(0, 8)}`;
  return {
    inviteUrl: `${origin}/auth/accept-invite?token=${token}&email=${encodeURIComponent(email)}`,
    inviteExpiresAt: new Date(Date.now() + 7 * DAY_MS).toISOString(),
  };
};

export const demoTenants: TenantsService = {
  async getTenants() {
    return structuredClone(tenantsMock);
  },

  async getTenant(id) {
    const found = tenantsMock.find((t) => t.id === id);
    return found ? structuredClone(found) : null;
  },

  async invite(input) {
    const now = new Date().toISOString();
    const created: Tenant = {
      id: crypto.randomUUID(),
      ...input,
      status: "invited",
      invitedAt: now,
      createdAt: now,
    };
    tenantsMock.push(created);
    return { tenant: structuredClone(created), ...demoInviteLink(created.email) };
  },

  async createInviteLink(id) {
    const tenant = tenantsMock.find((t) => t.id === id);
    if (!tenant) throw new Error(`Tenant ${id} not found`);
    if (tenant.status !== "invited") throw new Error("Only pending invites have an invite link");
    return demoInviteLink(tenant.email);
  },

  async update(id, patch) {
    const idx = tenantsMock.findIndex((t) => t.id === id);
    if (idx === -1) throw new Error(`Tenant ${id} not found`);
    const existing = tenantsMock[idx]!;
    const merged: Tenant = {
      ...existing,
      ...patch,
      personal: patch.personal
        ? { ...(existing.personal ?? {}), ...patch.personal }
        : existing.personal,
      emergencyContact: patch.emergencyContact
        ? { ...(existing.emergencyContact ?? {}), ...patch.emergencyContact }
        : existing.emergencyContact,
    };
    tenantsMock[idx] = merged;
    return structuredClone(merged);
  },

  async remove(id) {
    const idx = tenantsMock.findIndex((t) => t.id === id);
    if (idx !== -1) tenantsMock.splice(idx, 1);
  },

  // ── Tenant-shell scope ────────────────────────────────────────────────
  async getProfile(tenantId) {
    const found = tenantsMock.find((t) => t.id === tenantId);
    return found ? toProfile(found) : null;
  },

  async updateProfile(tenantId, patch) {
    const updated = await demoTenants.update(tenantId, patch);
    return toProfile(updated);
  },
};

const toProfile = (t: Tenant): TenantProfile =>
  structuredClone({
    id: t.id,
    name: t.name,
    email: t.email,
    phone: t.phone,
    personal: t.personal,
    emergencyContact: t.emergencyContact,
  });
