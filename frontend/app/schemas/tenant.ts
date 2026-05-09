import { z } from "zod";

const tenantStatusSchema = z.enum([
  "invited",
  "active",
  "notice_given",
  "moved_out",
]);

const phoneSchema = z
  .string()
  .regex(/^[\d\s+\-()]{8,20}$/, "Enter a valid phone number");

const optionalPhone = z
  .union([z.literal(""), phoneSchema])
  .optional();

const optionalIsoDate = z
  .union([z.literal(""), z.string().regex(/^\d{4}-\d{2}-\d{2}$/)])
  .optional();

export const tenantInputSchema = z.object({
  name: z.string().min(2).max(80),
  email: z.string().email(),
  phone: phoneSchema,
});

export const tenantIdentitySchema = z.object({
  name: z.string().min(2).max(80),
  email: z.string().email(),
  phone: phoneSchema,
  status: tenantStatusSchema,
});

export const tenantPersonalSchema = z.object({
  icNumber: z
    .union([
      z.literal(""),
      z.string().regex(/^\d{6}-\d{2}-\d{4}$/, "MyKad format YYMMDD-PB-####"),
    ])
    .optional(),
  dateOfBirth: optionalIsoDate,
  occupation: z.string().max(100).optional(),
  employer: z.string().max(100).optional(),
  monthlyIncome: z.number().nonnegative().optional(),
  nationality: z.string().max(50).optional(),
});

export const tenantEmergencyContactSchema = z.object({
  name: z.string().max(80).optional(),
  phone: optionalPhone,
  relationship: z.string().max(50).optional(),
});

export type TenantInputDto = z.infer<typeof tenantInputSchema>;
export type TenantIdentityDto = z.infer<typeof tenantIdentitySchema>;
export type TenantPersonalDto = z.infer<typeof tenantPersonalSchema>;
export type TenantEmergencyContactDto = z.infer<
  typeof tenantEmergencyContactSchema
>;
