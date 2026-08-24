import type {
  AttentionItem,
  DashboardData,
  DashboardService,
  IncomeBucket,
} from "~/services/contracts/dashboard";
import { propertiesMock } from "~/demo/data/properties";
import { unitsMock } from "~/demo/data/units";
import { tenantsMock } from "~/demo/data/tenants";
import { agreementsMock } from "~/demo/data/agreements";
import { invoicesMock, paymentsMock } from "~/demo/data/invoices";
import { ticketsMock } from "~/demo/data/tickets";

const DAY_MS = 24 * 60 * 60 * 1000;

const ymKey = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;

/**
 * Builds the dashboard payload from the in-memory demo data — the demo
 * mirror of the server-side `DashboardController`. Kept in lock-step with it.
 */
const buildFromDemoData = (): DashboardData => {
  const isEmpty = propertiesMock.length === 0;
  // Mirror DashboardController: only rental properties feed the stats/feed.
  const rentalIds = new Set(propertiesMock.filter((p) => p.purpose === "rental").map((p) => p.id));
  const rentalUnits = unitsMock.filter((u) => rentalIds.has(u.propertyId));
  const rentalUnitIds = new Set(rentalUnits.map((u) => u.id));
  const rentalAgreements = agreementsMock.filter((a) => rentalUnitIds.has(a.unitId));
  const rentalAgreementIds = new Set(rentalAgreements.map((a) => a.id));
  const rentalInvoices = invoicesMock.filter((i) => rentalAgreementIds.has(i.agreementId));
  const rentalInvoiceIds = new Set(rentalInvoices.map((i) => i.id));

  const unitCount = rentalUnits.length;
  const occupiedCount = rentalUnits.filter((u) => u.status === "occupied").length;
  const occupancyPct =
    unitCount > 0 ? Math.round((occupiedCount / unitCount) * 100) : 0;

  const outstandingInvoices = rentalInvoices.filter(
    (i) => i.status === "pending" || i.status === "overdue",
  );
  const outstanding = outstandingInvoices.reduce(
    (sum, i) => sum + i.amount + i.lateFee,
    0,
  );
  const outstandingCount = outstandingInvoices.length;

  const now = new Date();
  const thisMonth = ymKey(now);
  const successful = paymentsMock
    .filter((p) => rentalInvoiceIds.has(p.invoiceId))
    .filter((p) => p.status === "successful");
  const monthlyIncome = successful
    .filter((p) => ymKey(new Date(p.paidAt)) === thisMonth)
    .reduce((sum, p) => sum + p.amount, 0);

  const series: IncomeBucket[] = [];
  for (let i = 11; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    series.push({ key: ymKey(d), amount: 0 });
  }
  successful.forEach((p) => {
    const bucket = series.find((s) => s.key === ymKey(new Date(p.paidAt)));
    if (bucket) bucket.amount += p.amount;
  });

  const nowMs = now.getTime();
  const expiringAgreements = rentalAgreements.filter((a) => {
    if (a.status !== "active") return false;
    const end = new Date(a.endDate).getTime();
    return end >= nowMs && end - nowMs <= 60 * DAY_MS;
  });

  const needsAttention: AttentionItem[] = [];

  rentalInvoices
    .filter((i) => i.status === "overdue")
    .forEach((inv) => {
      const ag = agreementsMock.find((a) => a.id === inv.agreementId);
      const tenant = ag ? tenantsMock.find((t) => t.id === ag.tenantId) : null;
      needsAttention.push({
        kind: "overdue",
        title: inv.invoiceNumber,
        meta: tenant?.name ?? "—",
        link: "/owner/payments",
      });
    });

  expiringAgreements.forEach((a) => {
    const tenant = tenantsMock.find((t) => t.id === a.tenantId);
    const daysLeft = Math.ceil((new Date(a.endDate).getTime() - nowMs) / DAY_MS);
    needsAttention.push({
      kind: "expiring",
      title: tenant?.name ?? "Agreement",
      meta: `${daysLeft}d`,
      link: "/owner/agreements",
    });
  });

  tenantsMock
    .filter((t) => t.status === "notice_given")
    .forEach((t) => {
      needsAttention.push({
        kind: "notice_given",
        title: t.name,
        meta: "",
        link: `/owner/tenants/${t.id}`,
      });
    });

  ticketsMock
    .filter((t) => rentalUnitIds.has(t.unitId))
    .filter((t) => t.status === "new")
    .filter((t) => t.priority === "high" || t.priority === "urgent")
    .forEach((t) => {
      needsAttention.push({
        kind: "ticket_new",
        title: t.title,
        meta: t.priority,
        link: `/owner/maintenance/${t.id}`,
      });
    });

  ticketsMock
    .filter((t) => rentalUnitIds.has(t.unitId))
    .filter((t) => t.status === "reopened")
    .forEach((t) => {
      needsAttention.push({
        kind: "ticket_reopened",
        title: t.title,
        meta: t.priority,
        link: `/owner/maintenance/${t.id}`,
      });
    });

  return {
    isEmpty,
    stats: {
      monthlyIncome,
      occupancyPct,
      occupiedCount,
      unitCount,
      outstanding,
      outstandingCount,
      expiringCount: expiringAgreements.length,
    },
    incomeSeries: series,
    needsAttention,
  };
};

export const demoDashboard: DashboardService = {
  async getDashboard() {
    return buildFromDemoData();
  },
};
