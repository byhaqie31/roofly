import { computed, ref, type Ref } from "vue";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";
import type { Agreement } from "~/types/agreement";
import type { Invoice } from "~/types/invoice";
import type { Payment } from "~/types/payment";
import type { MonthlyBucket } from "~/composables/useDashboard";
import { computeCapitalGains, type CapitalGainsSnapshot } from "~/utils/rpgt";

const ymKey = (d: Date) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}`;

export interface PropertyReportRow {
  property: Property;
  unitsCount: number;
  occupiedCount: number;
  occupancyPct: number;
  incomeForYear: number;     // sen
  outstanding: number;       // sen
  gains: CapitalGainsSnapshot | null;
}

export const useReports = (year: Ref<number>) => {
  const loading = ref(true);
  const properties = ref<Property[]>([]);
  const units = ref<Unit[]>([]);
  const tenants = ref<Tenant[]>([]);
  const agreements = ref<Agreement[]>([]);
  const invoices = ref<Invoice[]>([]);
  const payments = ref<Payment[]>([]);

  const load = async () => {
    loading.value = true;
    try {
      const [props, us, ts, ags, invsWithRefs] = await Promise.all([
        useProperties().list(),
        useUnits().list(),
        useTenants().list(),
        useAgreements().list(),
        useInvoices().listWithRefs(),
      ]);
      properties.value = props;
      units.value = us;
      tenants.value = ts;
      agreements.value = ags;
      invoices.value = invsWithRefs.map((x) => x.invoice);
      payments.value = invsWithRefs.flatMap((x) => x.payments);
    } finally {
      loading.value = false;
    }
  };

  const successfulInYear = computed(() =>
    payments.value
      .filter((p) => p.status === "successful")
      .filter((p) => new Date(p.paidAt).getFullYear() === year.value),
  );

  const totalIncome = computed(() =>
    successfulInYear.value.reduce((sum, p) => sum + p.amount, 0),
  );

  const totalOutstanding = computed(() =>
    invoices.value
      .filter((i) => new Date(i.dueDate).getFullYear() === year.value)
      .filter((i) => i.status === "pending" || i.status === "overdue")
      .reduce((sum, i) => sum + i.amount + i.lateFee, 0),
  );

  const monthlyBreakdown = computed<MonthlyBucket[]>(() => {
    const series: MonthlyBucket[] = [];
    for (let m = 0; m < 12; m++) {
      const d = new Date(year.value, m, 1);
      series.push({
        key: ymKey(d),
        label: d.toLocaleDateString("en-MY", { month: "short" }),
        amount: 0,
      });
    }
    successfulInYear.value.forEach((p) => {
      const k = ymKey(new Date(p.paidAt));
      const bucket = series.find((s) => s.key === k);
      if (bucket) bucket.amount += p.amount;
    });
    return series;
  });

  const perProperty = computed<PropertyReportRow[]>(() => {
    return properties.value.map((prop) => {
      const propUnits = units.value.filter((u) => u.propertyId === prop.id);
      const propUnitIds = new Set(propUnits.map((u) => u.id));
      const propAgreements = agreements.value.filter((a) =>
        propUnitIds.has(a.unitId),
      );
      const propAgIds = new Set(propAgreements.map((a) => a.id));
      const propInvoices = invoices.value.filter((i) =>
        propAgIds.has(i.agreementId),
      );
      const propInvIds = new Set(propInvoices.map((i) => i.id));
      const propPayments = payments.value.filter((p) =>
        propInvIds.has(p.invoiceId),
      );

      const incomeForYear = propPayments
        .filter((p) => p.status === "successful")
        .filter((p) => new Date(p.paidAt).getFullYear() === year.value)
        .reduce((sum, p) => sum + p.amount, 0);

      const outstanding = propInvoices
        .filter((i) => i.status === "pending" || i.status === "overdue")
        .reduce((sum, i) => sum + i.amount + i.lateFee, 0);

      const occupied = propUnits.filter((u) => u.status === "occupied").length;
      const occupancyPct =
        propUnits.length > 0
          ? Math.round((occupied / propUnits.length) * 100)
          : 0;

      const gains = computeCapitalGains({
        purchasePrice: prop.ownership?.purchasePrice,
        stampDuty: prop.ownership?.stampDuty,
        legalFees: prop.ownership?.legalFees,
        currentMarketValue: prop.ownership?.currentMarketValue,
        purchaseDate: prop.ownership?.purchaseDate,
      });

      return {
        property: prop,
        unitsCount: propUnits.length,
        occupiedCount: occupied,
        occupancyPct,
        incomeForYear,
        outstanding,
        gains,
      };
    });
  });

  // Years that show up in any payment or invoice; falls back to current year.
  const availableYears = computed(() => {
    const set = new Set<number>();
    payments.value.forEach((p) => set.add(new Date(p.paidAt).getFullYear()));
    invoices.value.forEach((i) => set.add(new Date(i.dueDate).getFullYear()));
    if (set.size === 0) set.add(new Date().getFullYear());
    return Array.from(set).sort((a, b) => b - a);
  });

  const isEmpty = computed(() => properties.value.length === 0);

  return {
    load,
    loading,
    properties,
    totalIncome,
    totalOutstanding,
    monthlyBreakdown,
    perProperty,
    availableYears,
    isEmpty,
  };
};
