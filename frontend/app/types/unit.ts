export type UnitStatus = "vacant" | "occupied" | "maintenance";

export interface Unit {
  id: string;
  propertyId: string;
  label: string;
  bedrooms?: number;
  bathrooms?: number;
  sqft?: number;
  status: UnitStatus;
  createdAt: string;
}

export type UnitInput = Pick<
  Unit,
  "propertyId" | "label" | "bedrooms" | "bathrooms" | "sqft" | "status"
>;

export type UnitUpdate = Partial<
  Pick<Unit, "label" | "bedrooms" | "bathrooms" | "sqft" | "status">
>;
