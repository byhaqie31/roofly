import type { Tenant, TenantInput, TenantUpdate } from "~/types/tenant";
import { tenantsMock } from "~/mocks/tenants";

export const useTenants = () => {
  const { useMock } = useEnv();

  const list = async (): Promise<Tenant[]> => {
    if (useMock) return structuredClone(tenantsMock);
    const { request } = useApi();
    return request<Tenant[]>("/tenants");
  };

  const get = async (id: string): Promise<Tenant | null> => {
    if (useMock) {
      const found = tenantsMock.find((t) => t.id === id);
      return found ? structuredClone(found) : null;
    }
    const { request } = useApi();
    return request<Tenant>(`/tenants/${id}`);
  };

  const invite = async (input: TenantInput): Promise<Tenant> => {
    if (useMock) {
      const now = new Date().toISOString();
      const created: Tenant = {
        id: crypto.randomUUID(),
        ...input,
        status: "invited",
        invitedAt: now,
        createdAt: now,
      };
      tenantsMock.push(created);
      return structuredClone(created);
    }
    const { request } = useApi();
    return request<Tenant>("/tenants/invite", {
      method: "POST",
      body: input,
    });
  };

  const update = async (id: string, patch: TenantUpdate): Promise<Tenant> => {
    if (useMock) {
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
          ? {
              ...(existing.emergencyContact ?? {}),
              ...patch.emergencyContact,
            }
          : existing.emergencyContact,
      };
      tenantsMock[idx] = merged;
      return structuredClone(merged);
    }
    const { request } = useApi();
    return request<Tenant>(`/tenants/${id}`, { method: "PATCH", body: patch });
  };

  const remove = async (id: string): Promise<void> => {
    if (useMock) {
      const idx = tenantsMock.findIndex((t) => t.id === id);
      if (idx !== -1) tenantsMock.splice(idx, 1);
      return;
    }
    const { request } = useApi();
    await request(`/tenants/${id}`, { method: "DELETE" });
  };

  return { list, get, invite, update, remove };
};
