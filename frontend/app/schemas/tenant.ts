import { z } from "zod";
import { normalizeMyKad } from "~/utils/mykad";

const MYKAD_MESSAGE = "Enter the 12 digits of the MyKad number";
const isMyKad = (v: string) => normalizeMyKad(v) !== null;

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
  icNumber: z.union([z.literal(""), z.string().refine(isMyKad, MYKAD_MESSAGE)]).optional(),
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

// Tenant self-service profile form (tenant shell). Flat shape: identity +
// personal + emergency contact in one form. `monthlyIncomeRm` is edited in
// ringgit and converted to sen at the page edge; status is owner-controlled
// so it's intentionally absent here.
export const tenantProfileFormSchema = z.object({
  name: z.string().min(2).max(80),
  email: z.string().email(),
  phone: phoneSchema,
  icNumber: z.union([z.literal(""), z.string().refine(isMyKad, MYKAD_MESSAGE)]).optional(),
  dateOfBirth: optionalIsoDate,
  occupation: z.string().max(100).optional(),
  employer: z.string().max(100).optional(),
  monthlyIncomeRm: z.number().nonnegative().optional(),
  nationality: z.string().max(50).optional(),
  ecName: z.string().max(80).optional(),
  ecPhone: optionalPhone,
  ecRelationship: z.string().max(50).optional(),
});

// Tenant onboarding (spec 2026-10-07 § 4.4). Same flat shape as the profile
// form, but the core fields the landlord needs are required: phone, MyKad,
// emergency contact name + phone. Grouped into the three on-screen steps so
// each step validates only its own fields before advancing.
export const tenantOnboardingSchema = z.object({
  name: z.string().min(2).max(80),
  phone: phoneSchema,
  icNumber: z.string().refine(isMyKad, MYKAD_MESSAGE),
  dateOfBirth: optionalIsoDate,
  nationality: z.string().max(50).optional(),
  occupation: z.string().max(100).optional(),
  employer: z.string().max(100).optional(),
  monthlyIncomeRm: z.number().nonnegative().optional(),
  ecName: z.string().min(2, "Required").max(80),
  ecPhone: phoneSchema,
  ecRelationship: z.string().max(50).optional(),
});
export type TenantOnboardingDto = z.infer<typeof tenantOnboardingSchema>;

export const TENANT_ONBOARDING_STEPS = [
  { key: "about", fields: ["name", "phone", "icNumber", "dateOfBirth", "nationality"] },
  { key: "work", fields: ["occupation", "employer", "monthlyIncomeRm"] },
  { key: "emergency", fields: ["ecName", "ecPhone", "ecRelationship"] },
] as const satisfies readonly { key: string; fields: readonly (keyof TenantOnboardingDto)[] }[];

export type TenantInputDto = z.infer<typeof tenantInputSchema>;
export type TenantProfileFormDto = z.infer<typeof tenantProfileFormSchema>;
export type TenantIdentityDto = z.infer<typeof tenantIdentitySchema>;
export type TenantPersonalDto = z.infer<typeof tenantPersonalSchema>;
export type TenantEmergencyContactDto = z.infer<
  typeof tenantEmergencyContactSchema
>;
