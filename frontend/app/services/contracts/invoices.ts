import type { Invoice, InvoiceStatus } from "~/types/invoice";
import type { Payment, PaymentInput, PaymentMethod, TransferClaimInput } from "~/types/payment";
import type { Agreement } from "~/types/agreement";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";
import type { PayoutAccount } from "~/types/payout";

export interface InvoiceWithRefs {
  invoice: Invoice;
  agreement: Agreement | null;
  unit: Unit | null;
  property: Property | null;
  tenant: Tenant | null;
  payments: Payment[];
  /** Where to pay — resolved for the invoice's agreement (its own account ?? owner default ?? null). */
  payoutAccount: PayoutAccount | null;
}

export interface ClaimResult {
  payment: Payment;
  invoice: Invoice;
}

export interface InvoicesService {
  getInvoices(): Promise<Invoice[]>;
  getInvoicesWithRefs(): Promise<InvoiceWithRefs[]>;
  getInvoice(id: string): Promise<Invoice | null>;
  /** Tenant-shell scope: invoices across all of the tenant's agreements. API: `/me/invoices`. */
  getInvoicesForTenant(tenantId: string): Promise<InvoiceWithRefs[]>;
  updateStatus(id: string, status: InvoiceStatus): Promise<Invoice>;
  recordPayment(
    input: PaymentInput,
  ): Promise<{ payment: Payment; invoice: Invoice }>;
  sendInvoice(id: string): Promise<{ sentAt: string }>;
  /**
   * Online payment (gateway) — Coming soon, behind `features.onlinePayments`.
   * Today a simulated FPX round-trip; the API 403s (`online_payments_unavailable`)
   * unless the backend's ONLINE_PAYMENTS is on. API: `POST /me/invoices/{id}/pay`.
   */
  payForTenant(
    invoiceId: string,
    method: PaymentMethod,
  ): Promise<{ payment: Payment; invoice: Invoice }>;

  // ── Manual DuitNow claims (spec 2026-10-08) ──
  /**
   * Tenant: "I've paid" → a `pending` transfer payment; the invoice status is
   * unchanged until the owner confirms. 409 `claim_pending` if one is open,
   * 422 `no_payout_account` if the owner has no account. API: `POST /me/invoices/{id}/claim`.
   */
  claimTransferForTenant(invoiceId: string, input: TransferClaimInput): Promise<ClaimResult>;
  /** Owner: pending → successful, invoice → paid. `paidAt` corrects the date. API: `POST /payments/{id}/confirm`. */
  confirmClaim(paymentId: string, input?: { paidAt?: string }): Promise<ClaimResult>;
  /** Owner: pending → failed with a reason the tenant sees. API: `POST /payments/{id}/reject`. */
  rejectClaim(paymentId: string, reason: string): Promise<ClaimResult>;
}
