import type { Unit, UnitInput, UnitUpdate } from "~/types/unit";
import { unitsMock } from "~/mocks/units";

export const useUnits = () => {
  const { useMock } = useEnv();

  const list = async (): Promise<Unit[]> => {
    if (useMock) return structuredClone(unitsMock);
    const { request } = useApi();
    return request<Unit[]>("/units");
  };

  const listByProperty = async (propertyId: string): Promise<Unit[]> => {
    if (useMock) {
      return structuredClone(
        unitsMock.filter((u) => u.propertyId === propertyId),
      );
    }
    const { request } = useApi();
    return request<Unit[]>(`/properties/${propertyId}/units`);
  };

  const get = async (id: string): Promise<Unit | null> => {
    if (useMock) {
      const found = unitsMock.find((u) => u.id === id);
      return found ? structuredClone(found) : null;
    }
    const { request } = useApi();
    return request<Unit>(`/units/${id}`);
  };

  const create = async (input: UnitInput): Promise<Unit> => {
    if (useMock) {
      const created: Unit = {
        id: crypto.randomUUID(),
        ...input,
        createdAt: new Date().toISOString(),
      };
      unitsMock.push(created);
      return structuredClone(created);
    }
    const { request } = useApi();
    return request<Unit>(`/properties/${input.propertyId}/units`, {
      method: "POST",
      body: input,
    });
  };

  const update = async (id: string, patch: UnitUpdate): Promise<Unit> => {
    if (useMock) {
      const idx = unitsMock.findIndex((u) => u.id === id);
      if (idx === -1) throw new Error(`Unit ${id} not found`);
      const merged: Unit = { ...unitsMock[idx]!, ...patch };
      unitsMock[idx] = merged;
      return structuredClone(merged);
    }
    const { request } = useApi();
    return request<Unit>(`/units/${id}`, { method: "PATCH", body: patch });
  };

  const remove = async (id: string): Promise<void> => {
    if (useMock) {
      const idx = unitsMock.findIndex((u) => u.id === id);
      if (idx !== -1) unitsMock.splice(idx, 1);
      return;
    }
    const { request } = useApi();
    await request(`/units/${id}`, { method: "DELETE" });
  };

  return { list, listByProperty, get, create, update, remove };
};
