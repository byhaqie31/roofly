import { z } from "zod";

const agreementStatusSchema = z.enum([
  "draft",
  "active",
  "expired",
  "terminated",
]);

const ringgit = z.number().nonnegative();
const isoDate = z.string().regex(/^\d{4}-\d{2}-\d{2}$/, "Pick a date");

export const agreementFormSchema = z
  .object({
    unitId: z.string().min(1, "Pick a unit"),
    tenantId: z.string().min(1, "Pick a tenant"),
    startDate: isoDate,
    endDate: isoDate,
    rentAmount: ringgit,
    depositAmount: ringgit,
    lateFee: ringgit,
    rentDueDay: z.number().int().min(1).max(28),
    status: agreementStatusSchema,
  })
  .refine((data) => data.endDate > data.startDate, {
    message: "End date must be after start date",
    path: ["endDate"],
  });

export type AgreementFormDto = z.infer<typeof agreementFormSchema>;
