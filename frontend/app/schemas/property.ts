import { z } from "zod";
import { MY_STATES, type MalaysianState } from "~/types/property";

const propertyTypeSchema = z.enum(["condo", "landed", "shoplot", "room"]);
const furnishingSchema = z.enum(["unfurnished", "partial", "fully"]);
const titleTypeSchema = z.enum(["freehold", "leasehold"]);
const valuationSourceSchema = z.enum(["bank", "agent", "self"]);

const ringgit = z.number().nonnegative();
const optionalIsoDate = z
  .union([z.literal(""), z.string().regex(/^\d{4}-\d{2}-\d{2}$/)])
  .optional();

const currentYear = new Date().getFullYear();

// Add Property modal — Tier 1
export const propertyInputSchema = z.object({
  name: z.string().min(2).max(80),
  address: z.string().min(5).max(200),
  city: z.string().min(2).max(60),
  state: z.enum([...MY_STATES] as [MalaysianState, ...MalaysianState[]]),
  postcode: z.string().regex(/^\d{5}$/, "Postcode must be 5 digits"),
  type: propertyTypeSchema,
});

// Details tab — combined Identity + Location + Specifications
export const propertyDetailsFormSchema = z.object({
  // Identity
  name: z.string().min(2).max(80),
  internalLabel: z.string().max(40).optional(),
  type: propertyTypeSchema,
  notes: z.string().max(2000).optional(),
  // Location
  address: z.string().min(5).max(200),
  city: z.string().min(2).max(60),
  state: z.enum([...MY_STATES] as [MalaysianState, ...MalaysianState[]]),
  postcode: z.string().regex(/^\d{5}$/, "Postcode must be 5 digits"),
  // Specifications
  yearBuilt: z.number().int().min(1900).max(currentYear).optional(),
  builtUpSqft: z.number().positive().optional(),
  landSqft: z.number().positive().optional(),
  bedrooms: z.number().int().min(0).max(20).optional(),
  bathrooms: z.number().int().min(0).max(20).optional(),
  parkingLots: z.number().int().min(0).max(20).optional(),
  furnishing: furnishingSchema.optional(),
});

// Mortgage sub-schema (ringgit-mode for the form)
const mortgageFormSchema = z.object({
  bank: z.string().max(80).optional(),
  loanAmount: ringgit.optional(),
  outstandingBalance: ringgit.optional(),
  monthlyInstalment: ringgit.optional(),
  tenureYears: z.number().int().min(0).max(40).optional(),
  maturityDate: optionalIsoDate,
  interestRatePct: z.number().min(0).max(20).optional(),
});

// Co-owner row
const coOwnerSchema = z.object({
  id: z.string().min(1),
  name: z.string().min(1, "Name required"),
  sharePct: z.number().min(0).max(100),
  isPrimary: z.boolean(),
});

// Ownership tab — ringgit-mode for money. coOwners live alongside ownership
// because they're edited in the same form, but on save they're sent as a
// top-level patch (the backend stores them in a separate `property_co_owners` table).
export const propertyOwnershipFormSchema = z
  .object({
    titleType: titleTypeSchema.optional(),
    titleNumber: z.string().max(80).optional(),
    lotNumber: z.string().max(80).optional(),
    tenureExpiry: optionalIsoDate,
    strataTitle: z.boolean().optional(),
    masterTitle: z.boolean().optional(),
    purchaseDate: optionalIsoDate,
    purchasePrice: ringgit.optional(),
    stampDuty: ringgit.optional(),
    legalFees: ringgit.optional(),
    currentMarketValue: ringgit.optional(),
    lastValuedAt: optionalIsoDate,
    valuationSource: valuationSourceSchema.optional(),
    mortgage: mortgageFormSchema.optional(),
    coOwners: z.array(coOwnerSchema).min(1, "At least one co-owner is required"),
  })
  .refine(
    (data) => data.titleType !== "leasehold" || !!data.tenureExpiry,
    {
      message: "Tenure expiry is required for leasehold",
      path: ["tenureExpiry"],
    },
  )
  .refine(
    (data) =>
      data.coOwners.reduce((sum, c) => sum + (c.sharePct ?? 0), 0) === 100,
    {
      message: "Co-owner shares must sum to exactly 100%",
      path: ["coOwners"],
    },
  )
  .refine(
    (data) => data.coOwners.filter((c) => c.isPrimary).length === 1,
    {
      message: "Exactly one co-owner must be marked primary",
      path: ["coOwners"],
    },
  );

// Utilities tab — ringgit-mode for money
export const propertyUtilitiesFormSchema = z.object({
  monthlyMaintenanceFee: ringgit.optional(),
  sinkingFund: ringgit.optional(),
  quitRentAnnual: ringgit.optional(),
  assessmentRateAnnual: ringgit.optional(),
  buildingInsuranceAnnual: ringgit.optional(),
  tnbAccountNo: z.string().max(50).optional(),
  waterAccountNo: z.string().max(50).optional(),
  indahWaterAccountNo: z.string().max(50).optional(),
  internetAccountNo: z.string().max(50).optional(),
  managementCorpName: z.string().max(120).optional(),
  managementCorpPhone: z.string().max(40).optional(),
});

export type PropertyInputDto = z.infer<typeof propertyInputSchema>;
export type PropertyDetailsDto = z.infer<typeof propertyDetailsFormSchema>;
export type PropertyOwnershipDto = z.infer<typeof propertyOwnershipFormSchema>;
export type PropertyUtilitiesDto = z.infer<typeof propertyUtilitiesFormSchema>;
