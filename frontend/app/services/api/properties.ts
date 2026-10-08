import type { Property } from "~/types/property";
import type { PropertiesService } from "~/services/contracts/properties";

export const apiProperties: PropertiesService = {
  getProperties: () => useApi().request<Property[]>("/properties"),

  getProperty: (id) => useApi().request<Property>(`/properties/${id}`),

  create: (input) =>
    useApi().request<Property>("/properties", { method: "POST", body: input }),

  update: (id, patch) =>
    useApi().request<Property>(`/properties/${id}`, {
      method: "PATCH",
      body: patch,
    }),

  remove: async (id) => {
    await useApi().request(`/properties/${id}`, { method: "DELETE" });
  },
};
