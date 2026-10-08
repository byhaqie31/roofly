import type { Invoice } from "~/types/invoice";
import type {
  ClaimResult,
  InvoicesService,
  InvoiceWithRefs,
} from "~/services/contracts/invoices";

const EXPAND = "expand=agreement,unit,property,tenant,payments";

export const apiInvoices: InvoicesService = {
  getInvoices: () => useApi().request<Invoice[]>("/invoices"),

  getInvoicesWithRefs: () =>
    useApi().request<InvoiceWithRefs[]>(`/invoices?${EXPAND}`),

  getInvoice: (id) => useApi().request<Invoice>(`/invoices/${id}`),

  getInvoicesForTenant: () =>
    useApi().request<InvoiceWithRefs[]>(`/me/invoices?${EXPAND}`),

  updateStatus: (id, status) =>
    useApi().request<Invoice>(`/invoices/${id}`, {
      method: "PATCH",
      body: { status },
    }),

  recordPayment: (input) =>
    useApi().request(`/invoices/${input.invoiceId}/payments`, {
      method: "POST",
      body: input,
    }),

  sendInvoice: (id) =>
    useApi().request(`/invoices/${id}/send`, { method: "POST" }),

  payForTenant: (invoiceId, method) =>
    useApi().request(`/me/invoices/${invoiceId}/pay`, {
      method: "POST",
      body: { method },
    }),

  claimTransferForTenant: (invoiceId, input) =>
    useApi().request<ClaimResult>(`/me/invoices/${invoiceId}/claim`, {
      method: "POST",
      body: input,
    }),

  confirmClaim: (paymentId, input = {}) =>
    useApi().request<ClaimResult>(`/payments/${paymentId}/confirm`, {
      method: "POST",
      body: input,
    }),

  rejectClaim: (paymentId, reason) =>
    useApi().request<ClaimResult>(`/payments/${paymentId}/reject`, {
      method: "POST",
      body: { reason },
    }),
};
