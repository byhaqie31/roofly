import { z } from "zod";

const tenantStatusSchema = z.enum(["invited", "active", "moved_out"]);

const phoneSchema = z
  .string()
  .regex(/^[\d\s+\-()]{8,20}$/, "Enter a valid phone number");

export const tenantInputSchema = z.object({
  name: z.string().min(2).max(80),
  email: z.string().email(),
  phone: phoneSchema,
});

export const tenantUpdateSchema = z.object({
  name: z.string().min(2).max(80).optional(),
  email: z.string().email().optional(),
  phone: phoneSchema.optional(),
  status: tenantStatusSchema.optional(),
});

export type TenantInputDto = z.infer<typeof tenantInputSchema>;
export type TenantUpdateDto = z.infer<typeof tenantUpdateSchema>;
