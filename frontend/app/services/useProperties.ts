import type {
  Property,
  PropertyInput,
  PropertyUpdate,
} from "~/types/property";
import { propertiesMock } from "~/mocks/properties";

export const useProperties = () => {
  const { public: { useMock } } = useRuntimeConfig();

  const list = async (): Promise<Property[]> => {
    if (useMock) return structuredClone(propertiesMock);
    const { request } = useApi();
    return request<Property[]>("/properties");
  };

  const get = async (id: string): Promise<Property | null> => {
    if (useMock) {
      const found = propertiesMock.find((p) => p.id === id);
      return found ? structuredClone(found) : null;
    }
    const { request } = useApi();
    return request<Property>(`/properties/${id}`);
  };

  const create = async (input: PropertyInput): Promise<Property> => {
    if (useMock) {
      // Auto-insert the creating user as the primary co-owner with 100% share.
      // TODO: replace placeholder name once auth lands and we have the real user.
      const primaryCoOwnerId = crypto.randomUUID();
      const created: Property = {
        id: crypto.randomUUID(),
        ownerId: primaryCoOwnerId,
        ...input,
        coOwners: [
          {
            id: primaryCoOwnerId,
            name: "Primary owner",
            sharePct: 100,
            isPrimary: true,
          },
        ],
        createdAt: new Date().toISOString(),
      };
      propertiesMock.push(created);
      return structuredClone(created);
    }
    const { request } = useApi();
    return request<Property>("/properties", { method: "POST", body: input });
  };

  const update = async (
    id: string,
    patch: PropertyUpdate,
  ): Promise<Property> => {
    if (useMock) {
      const idx = propertiesMock.findIndex((p) => p.id === id);
      if (idx === -1) throw new Error(`Property ${id} not found`);
      const existing = propertiesMock[idx]!;
      const merged: Property = {
        ...existing,
        ...patch,
        ownership: patch.ownership
          ? { ...(existing.ownership ?? {}), ...patch.ownership }
          : existing.ownership,
        utilities: patch.utilities
          ? { ...(existing.utilities ?? {}), ...patch.utilities }
          : existing.utilities,
        // coOwners replaces wholesale (it's a list, not a partial object).
        // Keep ownerId in sync with whichever entry is marked primary.
        coOwners: patch.coOwners ?? existing.coOwners,
        ownerId: patch.coOwners
          ? (patch.coOwners.find((c) => c.isPrimary)?.id ?? existing.ownerId)
          : existing.ownerId,
      };
      propertiesMock[idx] = merged;
      return structuredClone(merged);
    }
    const { request } = useApi();
    return request<Property>(`/properties/${id}`, {
      method: "PATCH",
      body: patch,
    });
  };

  const remove = async (id: string): Promise<void> => {
    if (useMock) {
      const idx = propertiesMock.findIndex((p) => p.id === id);
      if (idx !== -1) propertiesMock.splice(idx, 1);
      return;
    }
    const { request } = useApi();
    await request(`/properties/${id}`, { method: "DELETE" });
  };

  return { list, get, create, update, remove };
};
