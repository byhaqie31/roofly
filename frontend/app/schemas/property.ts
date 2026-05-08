import { z } from "zod";
import { MY_STATES } from "~/types/property";

const propertyTypeSchema = z.enum(["condo", "landed", "shoplot", "room"]);
const furnishingSchema = z.enum(["unfurnished", "partial", "fully"]);
const titleTypeSchema = z.enum(["freehold", "leasehold"]);
const sen = z.number().int().nonnegative();

export const propertyInputSchema = z.object({
  name: z.string().min(2).max(80),
  address: z.string().min(5).max(200),
  city: z.string().min(2).max(60),
  state: z.enum([...MY_STATES] as [string, ...string[]]),
  postcode: z.string().regex(/^\d{5}$/, "Postcode must be 5 digits"),
  type: propertyTypeSchema,
});

const currentYear = new Date().getFullYear();

export const propertyBasicsSchema = z
  .object({
    yearBuilt: z.number().int().min(1900).max(currentYear).optional(),
    builtUpSqft: z.number().positive().optional(),
    landSqft: z.number().positive().optional(),
    bedrooms: z.number().int().min(0).max(20).optional(),
    bathrooms: z.number().int().min(0).max(20).optional(),
    parkingLots: z.number().int().min(0).max(20).optional(),
    furnishing: furnishingSchema.optional(),
    titleType: titleTypeSchema.optional(),
    tenureExpiry: z.string().optional(),
    strataTitle: z.boolean().optional(),
  })
  .refine(
    (data) => data.titleType !== "leasehold" || !!data.tenureExpiry,
    {
      message: "Tenure expiry is required for leasehold",
      path: ["tenureExpiry"],
    },
  );

export const propertyDetailsSchema = z.object({
  purchaseDate: z.string().optional(),
  purchasePrice: sen.optional(),
  monthlyMaintenanceFee: sen.optional(),
  quitRentAnnual: sen.optional(),
  assessmentRateAnnual: sen.optional(),
  insurancePolicyNo: z.string().max(100).optional(),
  insuranceProvider: z.string().max(100).optional(),
  tnbAccountNo: z.string().max(50).optional(),
  waterAccountNo: z.string().max(50).optional(),
  indahWaterAccountNo: z.string().max(50).optional(),
  notes: z.string().max(2000).optional(),
  photos: z.array(z.string().url()).optional(),
});

export type PropertyInputDto = z.infer<typeof propertyInputSchema>;
export type PropertyBasicsDto = z.infer<typeof propertyBasicsSchema>;
export type PropertyDetailsDto = z.infer<typeof propertyDetailsSchema>;

const ringgit = z.number().nonnegative();

export const propertyDetailsFormSchema = z.object({
  purchaseDate: z.string().optional(),
  purchasePrice: ringgit.optional(),
  monthlyMaintenanceFee: ringgit.optional(),
  quitRentAnnual: ringgit.optional(),
  assessmentRateAnnual: ringgit.optional(),
  insurancePolicyNo: z.string().max(100).optional(),
  insuranceProvider: z.string().max(100).optional(),
  tnbAccountNo: z.string().max(50).optional(),
  waterAccountNo: z.string().max(50).optional(),
  indahWaterAccountNo: z.string().max(50).optional(),
  notes: z.string().max(2000).optional(),
});

export type PropertyDetailsFormDto = z.infer<typeof propertyDetailsFormSchema>;
