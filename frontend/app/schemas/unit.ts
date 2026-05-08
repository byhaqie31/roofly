import { z } from "zod";

const unitStatusSchema = z.enum(["vacant", "occupied", "maintenance"]);

export const unitInputSchema = z.object({
  propertyId: z.string().min(1),
  label: z.string().min(1).max(80),
  bedrooms: z.number().int().min(0).max(20).optional(),
  bathrooms: z.number().int().min(0).max(20).optional(),
  sqft: z.number().positive().optional(),
  status: unitStatusSchema,
});

export type UnitInputDto = z.infer<typeof unitInputSchema>;
