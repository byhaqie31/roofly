import type { Unit } from "~/types/unit";
import type { UnitsService } from "~/services/contracts/units";
import { unitsMock } from "~/demo/data/units";

export const demoUnits: UnitsService = {
  async getUnits() {
    return structuredClone(unitsMock);
  },

  async getUnitsByProperty(propertyId) {
    return structuredClone(unitsMock.filter((u) => u.propertyId === propertyId));
  },

  async getUnit(id) {
    const found = unitsMock.find((u) => u.id === id);
    return found ? structuredClone(found) : null;
  },

  async create(input) {
    const created: Unit = {
      id: crypto.randomUUID(),
      ...input,
      createdAt: new Date().toISOString(),
    };
    unitsMock.push(created);
    return structuredClone(created);
  },

  async update(id, patch) {
    const idx = unitsMock.findIndex((u) => u.id === id);
    if (idx === -1) throw new Error(`Unit ${id} not found`);
    const merged: Unit = { ...unitsMock[idx]!, ...patch };
    unitsMock[idx] = merged;
    return structuredClone(merged);
  },

  async remove(id) {
    const idx = unitsMock.findIndex((u) => u.id === id);
    if (idx !== -1) unitsMock.splice(idx, 1);
  },
};
