import { computed, ref } from "vue";
import type {
  AttentionItem,
  DashboardData,
  DashboardStats,
  MonthlyBucket,
} from "~/services/contracts/dashboard";
import { demoDashboard } from "~/demo/services/dashboard";
import { apiDashboard } from "~/services/api/dashboard";

export type {
  AttentionItem,
  AttentionKind,
  DashboardData,
  DashboardStats,
  IncomeBucket,
  MonthlyBucket,
} from "~/services/contracts/dashboard";

const EMPTY_STATS: DashboardStats = {
  monthlyIncome: 0,
  occupancyPct: 0,
  occupiedCount: 0,
  unitCount: 0,
  outstanding: 0,
  outstandingCount: 0,
  expiringCount: 0,
};

/**
 * Owner dashboard state. One `getDashboard()` fetch — the API (or the demo
 * builder) computes stats, the income series, and the attention feed; the
 * only client-side work left is localizing month labels.
 */
export const useDashboard = () => {
  const service = useEnv().useMock ? demoDashboard : apiDashboard;

  const loading = ref(true);
  const data = ref<DashboardData | null>(null);

  const getDashboard = async () => {
    loading.value = true;
    try {
      data.value = await service.getDashboard();
    } finally {
      loading.value = false;
    }
  };

  const isEmpty = computed(() => data.value?.isEmpty ?? true);
  const stats = computed<DashboardStats>(() => data.value?.stats ?? EMPTY_STATS);
  const needsAttention = computed<AttentionItem[]>(
    () => data.value?.needsAttention ?? [],
  );

  // Localize month labels client-side so the server payload stays locale-free.
  const monthlyIncomeSeries = computed<MonthlyBucket[]>(() =>
    (data.value?.incomeSeries ?? []).map((b) => ({
      key: b.key,
      amount: b.amount,
      label: new Date(`${b.key}-01`).toLocaleDateString("en-MY", {
        month: "short",
      }),
    })),
  );

  return {
    getDashboard,
    loading,
    isEmpty,
    stats,
    needsAttention,
    monthlyIncomeSeries,
  };
};
