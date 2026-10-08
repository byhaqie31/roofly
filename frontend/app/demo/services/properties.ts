import type { Property } from "~/types/property";
import type { PropertiesService } from "~/services/contracts/properties";
import { propertiesMock } from "~/demo/data/properties";

export const demoProperties: PropertiesService = {
  async getProperties() {
    return structuredClone(propertiesMock);
  },

  async getProperty(id) {
    const found = propertiesMock.find((p) => p.id === id);
    return found ? structuredClone(found) : null;
  },

  async create(input) {
    // Auto-insert the creating user as the primary co-owner with 100% share.
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
  },

  async update(id, patch) {
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
  },

  async remove(id) {
    const idx = propertiesMock.findIndex((p) => p.id === id);
    if (idx !== -1) propertiesMock.splice(idx, 1);
  },
};
