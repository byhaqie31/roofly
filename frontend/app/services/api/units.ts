import type { Unit } from "~/types/unit";
import type { UnitsService } from "~/services/contracts/units";

export const apiUnits: UnitsService = {
  getUnits: () => useApi().request<Unit[]>("/units"),

  getUnitsByProperty: (propertyId) =>
    useApi().request<Unit[]>(`/properties/${propertyId}/units`),

  getUnit: (id) => useApi().request<Unit>(`/units/${id}`),

  create: (input) =>
    useApi().request<Unit>(`/properties/${input.propertyId}/units`, {
      method: "POST",
      body: input,
    }),

  update: (id, patch) =>
    useApi().request<Unit>(`/units/${id}`, { method: "PATCH", body: patch }),

  remove: async (id) => {
    await useApi().request(`/units/${id}`, { method: "DELETE" });
  },
};
