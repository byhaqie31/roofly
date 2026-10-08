import { z } from "zod";
import { BANKS } from "~/config/banks";

const bankSlugs = BANKS.map((b) => b.value) as [string, ...string[]];

/**
 * Settings → Payouts form (spec 2026-10-08 § 3.1). Same rules as the backend
 * FormRequest: at least one way to pay, and a DuitNow ID needs its type.
 */
export const payoutAccountFormSchema = z
  .object({
    label: z.string().trim().min(2).max(60),
    bank: z.enum(bankSlugs),
    accountHolderName: z.string().trim().min(2).max(120),
    accountNumber: z
      .union([z.literal(""), z.string().regex(/^[\d\s-]{6,30}$/, "Enter the account number (digits only)")])
      .optional(),
    duitnowIdType: z.union([z.literal(""), z.enum(["phone", "mykad", "brn", "passport"])]).optional(),
    duitnowId: z.union([z.literal(""), z.string().trim().max(40)]).optional(),
  })
  .superRefine((v, ctx) => {
    if (!v.accountNumber && !v.duitnowId) {
      ctx.addIssue({ code: "custom", path: ["accountNumber"], message: "Add an account number or a DuitNow ID" });
    }
    if (v.duitnowId && !v.duitnowIdType) {
      ctx.addIssue({ code: "custom", path: ["duitnowIdType"], message: "Pick the DuitNow ID type" });
    }
    if (v.duitnowIdType && !v.duitnowId) {
      ctx.addIssue({ code: "custom", path: ["duitnowId"], message: "Enter the DuitNow ID" });
    }
  });

export type PayoutAccountFormDto = z.infer<typeof payoutAccountFormSchema>;

/** Tenant "I've paid" (spec § 4 `POST /me/invoices/{id}/claim`). */
export const transferClaimFormSchema = z.object({
  reference: z.string().trim().min(3, "Enter the reference from your bank").max(100),
  paidAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, "Pick a date"),
  note: z.union([z.literal(""), z.string().max(500)]).optional(),
});

export type TransferClaimFormDto = z.infer<typeof transferClaimFormSchema>;
