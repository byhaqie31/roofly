import { z } from "zod";

const paymentMethodSchema = z.enum(["fpx", "card", "cash", "transfer"]);

export const recordPaymentFormSchema = z.object({
  amount: z.number().positive(),       // ringgit (form-mode)
  method: paymentMethodSchema,
  paidAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, "Pick a date"),
  reference: z.string().max(120).optional(),
});

export type RecordPaymentFormDto = z.infer<typeof recordPaymentFormSchema>;
