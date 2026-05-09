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
}

export type PaymentInput = Pick<
  Payment,
  "invoiceId" | "amount" | "method" | "paidAt" | "reference"
>;
