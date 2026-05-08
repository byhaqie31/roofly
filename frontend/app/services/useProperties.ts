import type {
  Property,
  PropertyInput,
  PropertyUpdate,
} from "~/types/property";
import { propertiesMock } from "~/mocks/properties";

const USE_MOCK = true;

export const useProperties = () => {
  const list = async (): Promise<Property[]> => {
    if (USE_MOCK) return structuredClone(propertiesMock);
    const { request } = useApi();
    return request<Property[]>("/properties");
  };

  const get = async (id: string): Promise<Property | null> => {
    if (USE_MOCK) {
      const found = propertiesMock.find((p) => p.id === id);
      return found ? structuredClone(found) : null;
    }
    const { request } = useApi();
    return request<Property>(`/properties/${id}`);
  };

  const create = async (input: PropertyInput): Promise<Property> => {
    if (USE_MOCK) {
      const created: Property = {
        id: crypto.randomUUID(),
        ownerId: "owner-1",
        ...input,
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
    if (USE_MOCK) {
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
    if (USE_MOCK) {
      const idx = propertiesMock.findIndex((p) => p.id === id);
      if (idx !== -1) propertiesMock.splice(idx, 1);
      return;
    }
    const { request } = useApi();
    await request(`/properties/${id}`, { method: "DELETE" });
  };

  return { list, get, create, update, remove };
};
