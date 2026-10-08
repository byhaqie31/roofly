import type { InvoicesService } from "~/services/contracts/invoices";
import { demoInvoices } from "~/demo/services/invoices";
import { apiInvoices } from "~/services/api/invoices";

export type { InvoiceWithRefs } from "~/services/contracts/invoices";

/** Demo → in-memory seed data; otherwise the Laravel API. Chosen once per call. */
export const useInvoices = (): InvoicesService =>
  useEnv().useMock ? demoInvoices : apiInvoices;
