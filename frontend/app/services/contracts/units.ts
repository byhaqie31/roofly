import type { Unit, UnitInput, UnitUpdate } from "~/types/unit";

export interface UnitsService {
  getUnits(): Promise<Unit[]>;
  getUnitsByProperty(propertyId: string): Promise<Unit[]>;
  getUnit(id: string): Promise<Unit | null>;
  create(input: UnitInput): Promise<Unit>;
  update(id: string, patch: UnitUpdate): Promise<Unit>;
  remove(id: string): Promise<void>;
}
