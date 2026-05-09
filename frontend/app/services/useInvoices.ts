import type { Invoice, InvoiceStatus } from "~/types/invoice";
import type { Payment, PaymentInput } from "~/types/payment";
import type { Agreement } from "~/types/agreement";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";
import { invoicesMock, paymentsMock } from "~/mocks/invoices";
import { agreementsMock } from "~/mocks/agreements";
import { unitsMock } from "~/mocks/units";
import { propertiesMock } from "~/mocks/properties";
import { tenantsMock } from "~/mocks/tenants";

export interface InvoiceWithRefs {
  invoice: Invoice;
  agreement: Agreement | null;
  unit: Unit | null;
  property: Property | null;
  tenant: Tenant | null;
  payments: Payment[];
}

const hydrate = (inv: Invoice): InvoiceWithRefs => {
  const agreement = agreementsMock.find((a) => a.id === inv.agreementId) ?? null;
  const unit = agreement
    ? (unitsMock.find((u) => u.id === agreement.unitId) ?? null)
    : null;
  const property = unit
    ? (propertiesMock.find((p) => p.id === unit.propertyId) ?? null)
    : null;
  const tenant = agreement
    ? (tenantsMock.find((t) => t.id === agreement.tenantId) ?? null)
    : null;
  const payments = paymentsMock.filter((p) => p.invoiceId === inv.id);
  return {
    invoice: structuredClone(inv),
    agreement: agreement ? structuredClone(agreement) : null,
    unit: unit ? structuredClone(unit) : null,
    property: property ? structuredClone(property) : null,
    tenant: tenant ? structuredClone(tenant) : null,
    payments: structuredClone(payments),
  };
};

export const useInvoices = () => {
  const { public: { useMock } } = useRuntimeConfig();

  const list = async (): Promise<Invoice[]> => {
    if (useMock) return structuredClone(invoicesMock);
    const { request } = useApi();
    return request<Invoice[]>("/invoices");
  };

  const listWithRefs = async (): Promise<InvoiceWithRefs[]> => {
    if (useMock) return invoicesMock.map(hydrate);
    const { request } = useApi();
    return request<InvoiceWithRefs[]>("/invoices?expand=agreement,unit,property,tenant,payments");
  };

  const get = async (id: string): Promise<Invoice | null> => {
    if (useMock) {
      const found = invoicesMock.find((i) => i.id === id);
      return found ? structuredClone(found) : null;
    }
    const { request } = useApi();
    return request<Invoice>(`/invoices/${id}`);
  };

  const updateStatus = async (
    id: string,
    status: InvoiceStatus,
  ): Promise<Invoice> => {
    if (useMock) {
      const idx = invoicesMock.findIndex((i) => i.id === id);
      if (idx === -1) throw new Error(`Invoice ${id} not found`);
      invoicesMock[idx] = { ...invoicesMock[idx]!, status };
      return structuredClone(invoicesMock[idx]!);
    }
    const { request } = useApi();
    return request<Invoice>(`/invoices/${id}`, {
      method: "PATCH",
      body: { status },
    });
  };

  const recordPayment = async (
    input: PaymentInput,
  ): Promise<{ payment: Payment; invoice: Invoice }> => {
    if (useMock) {
      const now = new Date().toISOString();
      const payment: Payment = {
        id: crypto.randomUUID(),
        ...input,
        status: "successful",
        createdAt: now,
      };
      paymentsMock.push(payment);
      const idx = invoicesMock.findIndex((i) => i.id === input.invoiceId);
      if (idx === -1) throw new Error(`Invoice ${input.invoiceId} not found`);
      invoicesMock[idx] = { ...invoicesMock[idx]!, status: "paid" };
      return {
        payment: structuredClone(payment),
        invoice: structuredClone(invoicesMock[idx]!),
      };
    }
    const { request } = useApi();
    return request(`/invoices/${input.invoiceId}/payments`, {
      method: "POST",
      body: input,
    });
  };

  const sendInvoice = async (id: string): Promise<{ sentAt: string }> => {
    if (useMock) {
      // No persistent state for "lastSentAt" yet — backend will own that.
      return { sentAt: new Date().toISOString() };
    }
    const { request } = useApi();
    return request(`/invoices/${id}/send`, { method: "POST" });
  };

  return { list, listWithRefs, get, updateStatus, recordPayment, sendInvoice };
};
