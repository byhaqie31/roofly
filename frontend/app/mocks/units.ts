import type { Unit } from "~/types/unit";

export const unitsMock: Unit[] = [
  {
    id: "u-suria-1",
    propertyId: "11111111-1111-1111-1111-111111111111",
    label: "Whole unit",
    bedrooms: 3,
    bathrooms: 2,
    sqft: 1100,
    status: "occupied",
    createdAt: "2026-01-12T09:30:00Z",
  },
  {
    id: "u-ttdi-1",
    propertyId: "22222222-2222-2222-2222-222222222222",
    label: "Whole house",
    bedrooms: 4,
    bathrooms: 3,
    sqft: 2400,
    status: "vacant",
    createdAt: "2026-02-03T11:00:00Z",
  },
  {
    id: "u-wangsa-1",
    propertyId: "33333333-3333-3333-3333-333333333333",
    label: "Ground floor shop",
    sqft: 600,
    status: "occupied",
    createdAt: "2026-03-18T14:30:00Z",
  },
  {
    id: "u-wangsa-2",
    propertyId: "33333333-3333-3333-3333-333333333333",
    label: "Upper level office",
    sqft: 600,
    status: "vacant",
    createdAt: "2026-03-18T14:35:00Z",
  },
  {
    id: "u-usj-1",
    propertyId: "44444444-4444-4444-4444-444444444444",
    label: "Spare bedroom",
    bedrooms: 1,
    bathrooms: 0,
    status: "maintenance",
    createdAt: "2026-04-22T08:30:00Z",
  },
];
