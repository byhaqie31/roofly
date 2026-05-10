import { computed, ref } from "vue";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";
import type { Agreement } from "~/types/agreement";
import type { Invoice } from "~/types/invoice";
import type { Payment } from "~/types/payment";
import type { Ticket } from "~/types/ticket";

const DAY_MS = 24 * 60 * 60 * 1000;

const ymKey = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;

export type AttentionKind =
  | "overdue"
  | "expiring"
  | "notice_given"
  | "ticket_new"
  | "ticket_reopened";

export interface AttentionItem {
  kind: AttentionKind;
  title: string;
  meta: string;
  link: string;
}

export interface MonthlyBucket {
  key: string;          // YYYY-MM
  label: string;        // localized short month name
  amount: number;       // sen
}

export const useDashboard = () => {
  const loading = ref(true);
  const properties = ref<Property[]>([]);
  const units = ref<Unit[]>([]);
  const tenants = ref<Tenant[]>([]);
  const agreements = ref<Agreement[]>([]);
  const invoices = ref<Invoice[]>([]);
  const payments = ref<Payment[]>([]);
  const tickets = ref<Ticket[]>([]);

  const load = async () => {
    loading.value = true;
    try {
      const [props, us, ts, ags, invsWithRefs, tk] = await Promise.all([
        useProperties().list(),
        useUnits().list(),
        useTenants().list(),
        useAgreements().list(),
        useInvoices().listWithRefs(),
        useTickets().list(),
      ]);
      properties.value = props;
      units.value = us;
      tenants.value = ts;
      agreements.value = ags;
      invoices.value = invsWithRefs.map((x) => x.invoice);
      payments.value = invsWithRefs.flatMap((x) => x.payments);
      tickets.value = tk;
    } finally {
      loading.value = false;
    }
  };

  const monthlyIncome = computed(() => {
    const thisMonth = ymKey(new Date());
    return payments.value
      .filter((p) => p.status === "successful")
      .filter((p) => ymKey(new Date(p.paidAt)) === thisMonth)
      .reduce((sum, p) => sum + p.amount, 0);
  });

  const occupancyPct = computed(() => {
    if (units.value.length === 0) return 0;
    const occupied = units.value.filter((u) => u.status === "occupied").length;
    return Math.round((occupied / units.value.length) * 100);
  });

  const outstanding = computed(() =>
    invoices.value
      .filter((i) => i.status === "pending" || i.status === "overdue")
      .reduce((sum, i) => sum + i.amount + i.lateFee, 0),
  );

  const expiringCount = computed(() => {
    const now = Date.now();
    const cutoff = now + 60 * DAY_MS;
    return agreements.value.filter((a) => {
      if (a.status !== "active") return false;
      const end = new Date(a.endDate).getTime();
      return end >= now && end <= cutoff;
    }).length;
  });

  // Trailing 12 months ending in current month, oldest first.
  const monthlyIncomeSeries = computed<MonthlyBucket[]>(() => {
    const series: MonthlyBucket[] = [];
    const now = new Date();
    for (let i = 11; i >= 0; i--) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      series.push({
        key: ymKey(d),
        label: d.toLocaleDateString("en-MY", { month: "short" }),
        amount: 0,
      });
    }
    payments.value
      .filter((p) => p.status === "successful")
      .forEach((p) => {
        const k = ymKey(new Date(p.paidAt));
        const bucket = series.find((s) => s.key === k);
        if (bucket) bucket.amount += p.amount;
      });
    return series;
  });

  const needsAttention = computed<AttentionItem[]>(() => {
    const items: AttentionItem[] = [];

    invoices.value
      .filter((i) => i.status === "overdue")
      .forEach((inv) => {
        const ag = agreements.value.find((a) => a.id === inv.agreementId);
        const tenant = ag
          ? tenants.value.find((t) => t.id === ag.tenantId)
          : null;
        items.push({
          kind: "overdue",
          title: inv.invoiceNumber,
          meta: tenant?.name ?? "—",
          link: "/owner/payments",
        });
      });

    const now = Date.now();
    agreements.value
      .filter((a) => a.status === "active")
      .filter((a) => {
        const end = new Date(a.endDate).getTime();
        return end >= now && end - now <= 60 * DAY_MS;
      })
      .forEach((a) => {
        const tenant = tenants.value.find((t) => t.id === a.tenantId);
        const daysLeft = Math.ceil(
          (new Date(a.endDate).getTime() - now) / DAY_MS,
        );
        items.push({
          kind: "expiring",
          title: tenant?.name ?? "Agreement",
          meta: `${daysLeft}d`,
          link: "/owner/agreements",
        });
      });

    tenants.value
      .filter((t) => t.status === "notice_given")
      .forEach((t) => {
        items.push({
          kind: "notice_given",
          title: t.name,
          meta: "",
          link: `/owner/tenants/${t.id}`,
        });
      });

    // High/urgent new tickets — low/medium new tickets stay in the Kanban
    // so they don't crowd the feed.
    tickets.value
      .filter((t) => t.status === "new")
      .filter((t) => t.priority === "high" || t.priority === "urgent")
      .forEach((t) => {
        items.push({
          kind: "ticket_new",
          title: t.title,
          meta: t.priority,
          link: `/owner/maintenance/${t.id}`,
        });
      });

    // All reopened tickets surface — by definition they need attention again.
    tickets.value
      .filter((t) => t.status === "reopened")
      .forEach((t) => {
        items.push({
          kind: "ticket_reopened",
          title: t.title,
          meta: t.priority,
          link: `/owner/maintenance/${t.id}`,
        });
      });

    return items;
  });

  const isEmpty = computed(() => properties.value.length === 0);

  return {
    load,
    loading,
    properties,
    units,
    tenants,
    agreements,
    invoices,
    payments,
    monthlyIncome,
    occupancyPct,
    outstanding,
    expiringCount,
    monthlyIncomeSeries,
    needsAttention,
    isEmpty,
  };
};
