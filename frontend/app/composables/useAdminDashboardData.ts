import { computed, ref } from "vue";
import type { AdminAttentionItem, AdminDashboardData } from "~/types/admin";

const EMPTY: AdminDashboardData = {
  tiles: {
    owners: { total: 0, active: 0, suspended: 0 }, tenants: { total: 0, invitedPending: 0 },
    properties: 0, units: { total: 0, occupiedPct: 0 }, agreementsActive: 0, agreementsExpiring30d: 0, supportOpen: 0,
  },
  series: { months: [], ownerSignups: [], invoicesIssued: [], invoicesPaid: [], inviteAcceptanceRate: [] },
  attention: [],
};

/** Mirrors useDashboard(): one fetch, localised month labels client-side. */
export const useAdminDashboardData = () => {
  const loading = ref(true);
  const data = ref<AdminDashboardData | null>(null);

  const load = async () => {
    loading.value = true;
    try {
      data.value = await useAdminDashboard().getDashboard();
    } finally {
      loading.value = false;
    }
  };

  const label = (key: string) => new Date(`${key}-01`).toLocaleDateString("en-MY", { month: "short" });
  const series = (pick: (d: AdminDashboardData["series"]) => number[]) =>
    computed(() => {
      const s = data.value?.series ?? EMPTY.series;
      return s.months.map((key, i) => ({ key, label: label(key), amount: pick(s)[i] ?? 0 }));
    });

  return {
    load,
    loading,
    data,
    tiles: computed(() => data.value?.tiles ?? EMPTY.tiles),
    attention: computed<AdminAttentionItem[]>(() => data.value?.attention ?? []),
    signupSeries: series((s) => s.ownerSignups),
    invoiceSeries: series((s) => s.invoicesPaid),
    acceptanceSeries: series((s) => s.inviteAcceptanceRate),
  };
};
