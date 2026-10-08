export type PaymentMethod = "fpx" | "card" | "cash" | "transfer";
export type PaymentStatus = "pending" | "successful" | "failed";

export interface Payment {
  id: string;
  invoiceId: string;
  amount: number;            // sen
  method: PaymentMethod;
  status: PaymentStatus;
  paidAt: string;            // ISO datetime
  reference?: string;        // free-form transaction ref
  createdAt: string;
  // Manual DuitNow claims (spec 2026-10-08): a tenant's "I've paid" is a
  // `pending` transfer the owner confirms (→ successful) or rejects (→ failed).
  payoutAccountId?: string | null;  // the account the tenant was told to pay
  note?: string | null;             // tenant's note on the claim
  rejectionReason?: string | null;
  confirmedAt?: string | null;
}

/** Tenant's "I've paid" — `paidAt` is the date of the transfer (YYYY-MM-DD). */
export interface TransferClaimInput {
  reference: string;
  paidAt: string;
  note?: string;
}

export type PaymentInput = Pick<
  Payment,
  "invoiceId" | "amount" | "method" | "paidAt" | "reference"
>;
