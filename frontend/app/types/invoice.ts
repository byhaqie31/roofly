export type InvoiceStatus = "pending" | "paid" | "overdue" | "cancelled";

export interface Invoice {
  id: string;
  agreementId: string;
  invoiceNumber: string;     // INV-2026-001
  amount: number;            // sen — base rent for this period
  lateFee: number;           // sen — accrued late fee (snapshot)
  dueDate: string;           // ISO date
  status: InvoiceStatus;
  createdAt: string;
}
