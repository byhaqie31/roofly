import type { Agreement } from "~/types/agreement";
import type { Invoice, InvoiceStatus } from "~/types/invoice";
import type { Payment } from "~/types/payment";
import { agreementsMock } from "~/mocks/agreements";

const DAY_MS = 24 * 60 * 60 * 1000;

interface Generated {
  invoices: Invoice[];
  payments: Payment[];
}

const generateForAgreement = (a: Agreement, today: Date): Generated => {
  const invoices: Invoice[] = [];
  const payments: Payment[] = [];

  if (a.status === "draft") return { invoices, payments };

  const start = new Date(a.startDate);
  const end = new Date(a.endDate);
  const cutoff = new Date(Math.min(end.getTime(), today.getTime() + 30 * DAY_MS));

  let year = start.getFullYear();
  let month = start.getMonth();
  let counter = 0;

  while (true) {
    const dueDate = new Date(year, month, a.rentDueDay);
    if (dueDate > cutoff) break;

    counter += 1;
    const id = `inv-${a.id}-${counter.toString().padStart(2, "0")}`;
    const invoiceNumber = ""; // assigned later globally
    const dueIso = dueDate.toISOString().slice(0, 10);

    let status: InvoiceStatus;
    let lateFee = 0;
    let payment: Payment | null = null;

    if (a.status === "expired" || a.status === "terminated") {
      status = "paid";
    } else if (dueDate.getTime() + 30 * DAY_MS < today.getTime()) {
      // Due more than 30 days ago — assume settled
      status = "paid";
    } else if (dueDate < today) {
      // Due in the last 30 days, not yet settled
      status = "overdue";
      lateFee = a.lateFee;
    } else {
      status = "pending";
    }

    if (status === "paid") {
      const paidAt = new Date(dueDate.getTime() + 2 * DAY_MS);
      payment = {
        id: `pay-${id}`,
        invoiceId: id,
        amount: a.rentAmount,
        method: "fpx",
        status: "successful",
        paidAt: paidAt.toISOString(),
        reference: `FPX-${dueIso.replace(/-/g, "")}`,
        createdAt: paidAt.toISOString(),
      };
    }

    invoices.push({
      id,
      agreementId: a.id,
      invoiceNumber,
      amount: a.rentAmount,
      lateFee,
      dueDate: dueIso,
      status,
      createdAt: dueDate.toISOString(),
    });

    if (payment) payments.push(payment);

    month += 1;
    if (month > 11) {
      month = 0;
      year += 1;
    }
  }

  return { invoices, payments };
};

const today = new Date();
const all: Generated = { invoices: [], payments: [] };

agreementsMock.forEach((a) => {
  const out = generateForAgreement(a, today);
  all.invoices.push(...out.invoices);
  all.payments.push(...out.payments);
});

// Sequence invoice numbers chronologically across the whole portfolio
all.invoices
  .slice()
  .sort((a, b) => a.dueDate.localeCompare(b.dueDate))
  .forEach((inv, idx) => {
    inv.invoiceNumber = `INV-${(idx + 1).toString().padStart(4, "0")}`;
  });

export const invoicesMock: Invoice[] = all.invoices;
export const paymentsMock: Payment[] = all.payments;
