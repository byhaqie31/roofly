<script setup lang="ts">
import { computed, onMounted } from "vue";
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";
import MoneyDisplay from "~/components/ui/MoneyDisplay.vue";
import MiniAreaChart from "~/components/ui/MiniAreaChart.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import { useDashboard, type AttentionKind } from "~/composables/useDashboard";

definePageMeta({ layout: "owner" });

const { t } = useI18n();
useHead({ title: () => t("owner.dashboard.title") });

const dashboard = useDashboard();
const demoTour = useDemoTour();
onMounted(async () => {
  await dashboard.load();
  // Auto-start the product tour once per browser on demo. No-op elsewhere.
  demoTour.maybeAutoStart();
});

const occupiedCount = computed(
  () => dashboard.units.value.filter((u) => u.status === "occupied").length,
);
const outstandingCount = computed(
  () =>
    dashboard.invoices.value.filter(
      (i) => i.status === "pending" || i.status === "overdue",
    ).length,
);

// Trailing-12-month summary stats for the chart card.
const incomeTotal12mo = computed(() =>
  dashboard.monthlyIncomeSeries.value.reduce((sum, b) => sum + b.amount, 0),
);
const incomeAvg12mo = computed(() => {
  const series = dashboard.monthlyIncomeSeries.value;
  if (series.length === 0) return 0;
  return Math.round(incomeTotal12mo.value / series.length);
});
const bestMonth = computed(() => {
  const series = dashboard.monthlyIncomeSeries.value;
  if (series.length === 0) return null;
  return series.reduce(
    (best, b) => (b.amount > best.amount ? b : best),
    series[0]!,
  );
});

const attentionTone: Record<
  AttentionKind,
  "overdue" | "maintenance" | "draft" | "pending"
> = {
  overdue: "overdue",
  expiring: "maintenance",
  notice_given: "draft",
  ticket_new: "pending",
  ticket_reopened: "overdue",
};
</script>

<template>
  <div>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.dashboard.title") }}
      </h1>
      <p class="mt-2 text-caption text-ink-muted">
        {{ t("owner.dashboard.subtitle") }}
      </p>
    </header>

    <Card v-if="dashboard.loading.value" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("common.loading") }}
      </p>
    </Card>

    <Card v-else-if="dashboard.isEmpty.value" padding="loose">
      <EmptyState
        icon="Building2"
        :title="t('owner.dashboard.emptyState.title')"
        :description="t('owner.dashboard.emptyState.description')"
      >
        <template #action>
          <NuxtLink to="/owner/properties">
            <Button variant="primary" size="lg">
              {{ t("owner.dashboard.emptyState.cta") }}
            </Button>
          </NuxtLink>
        </template>
      </EmptyState>
    </Card>

    <template v-else>
      <section data-tour="stats" class="mb-6 grid grid-cols-1 gap-4 sm:mb-8 sm:grid-cols-2 sm:gap-6 2xl:grid-cols-4">
        <Card padding="standard">
          <p class="text-caption text-ink-muted">
            {{ t("owner.dashboard.incomeMonth") }}
          </p>
          <p class="mt-2 text-display-sub font-semibold tracking-snug">
            <MoneyDisplay :cents="dashboard.monthlyIncome.value" emphasis />
          </p>
          <p class="mt-1 text-micro text-ink-faint">
            {{ t("owner.dashboard.incomeMonthHelp") }}
          </p>
        </Card>
        <Card padding="standard">
          <p class="text-caption text-ink-muted">
            {{ t("owner.dashboard.occupancy") }}
          </p>
          <p class="mt-2 text-display-sub font-semibold tracking-snug num">
            {{ dashboard.occupancyPct.value }}%
          </p>
          <p class="mt-1 text-micro text-ink-faint">
            {{
              t("owner.dashboard.occupancyHelp", {
                occupied: occupiedCount,
                total: dashboard.units.value.length,
              })
            }}
          </p>
        </Card>
        <Card padding="standard">
          <p class="text-caption text-ink-muted">
            {{ t("owner.dashboard.outstanding") }}
          </p>
          <p class="mt-2 text-display-sub font-semibold tracking-snug">
            <MoneyDisplay :cents="dashboard.outstanding.value" emphasis />
          </p>
          <p class="mt-1 text-micro text-ink-faint">
            {{
              t("owner.dashboard.outstandingHelp", { count: outstandingCount })
            }}
          </p>
        </Card>
        <Card padding="standard">
          <p class="text-caption text-ink-muted">
            {{ t("owner.dashboard.expiringAgreements") }}
          </p>
          <p class="mt-2 text-display-sub font-semibold tracking-snug num">
            {{ dashboard.expiringCount.value }}
          </p>
          <p class="mt-1 text-micro text-ink-faint">
            {{ t("owner.dashboard.expiringHelp") }}
          </p>
        </Card>
      </section>

      <section data-tour="income-chart" class="mb-6 sm:mb-8">
        <Card padding="loose">
          <header class="mb-4 flex items-end justify-between">
            <div>
              <h2 class="text-card-title font-semibold text-ink">
                {{ t("owner.dashboard.incomeChart.title") }}
              </h2>
              <p class="mt-1 text-caption text-ink-muted">
                {{ t("owner.dashboard.incomeChart.help") }}
              </p>
            </div>
          </header>
          <MiniAreaChart :data="dashboard.monthlyIncomeSeries.value" :height="120" />
          <dl
            class="mt-6 grid grid-cols-1 gap-3 border-t border-line-passive pt-4 sm:grid-cols-3"
          >
            <div>
              <dt class="text-micro text-ink-faint">
                {{ t("owner.dashboard.incomeChart.total") }}
              </dt>
              <dd class="mt-0.5 text-body font-semibold text-ink">
                <MoneyDisplay :cents="incomeTotal12mo" />
              </dd>
            </div>
            <div>
              <dt class="text-micro text-ink-faint">
                {{ t("owner.dashboard.incomeChart.average") }}
              </dt>
              <dd class="mt-0.5 text-body font-semibold text-ink">
                <MoneyDisplay :cents="incomeAvg12mo" />
              </dd>
            </div>
            <div>
              <dt class="text-micro text-ink-faint">
                {{ t("owner.dashboard.incomeChart.bestMonth") }}
              </dt>
              <dd class="mt-0.5 text-body font-semibold text-ink">
                <span v-if="bestMonth && bestMonth.amount > 0">
                  {{ bestMonth.label }}
                  <span class="ml-1 text-caption font-normal text-ink-muted tabular-nums">
                    <MoneyDisplay :cents="bestMonth.amount" />
                  </span>
                </span>
                <span v-else class="text-ink-faint">—</span>
              </dd>
            </div>
          </dl>
        </Card>
      </section>

      <section data-tour="attention">
        <Card padding="loose">
          <header class="mb-4">
            <h2 class="text-card-title font-semibold text-ink">
              {{ t("owner.dashboard.attention.title") }}
            </h2>
            <p class="mt-1 text-caption text-ink-muted">
              {{ t("owner.dashboard.attention.help") }}
            </p>
          </header>

          <ul
            v-if="dashboard.needsAttention.value.length > 0"
            class="divide-y divide-line-passive"
          >
            <li
              v-for="(item, idx) in dashboard.needsAttention.value"
              :key="`${item.kind}-${idx}-${item.title}`"
            >
              <NuxtLink
                :to="item.link"
                class="group flex items-start gap-3 rounded-sm py-3 outline-none transition hover:bg-surface-hover focus-visible:shadow-focus"
              >
                <div class="min-w-0 flex-1">
                  <!-- Status pill + name/meta as secondary context -->
                  <div class="mb-1 flex items-center gap-2">
                    <Pill :tone="attentionTone[item.kind]" class="shrink-0">
                      {{ t(`owner.dashboard.attention.kinds.${item.kind}`) }}
                    </Pill>
                    <span
                      v-if="item.meta"
                      class="truncate text-caption text-ink-muted"
                    >
                      {{ item.meta }}
                    </span>
                  </div>
                  <!-- Main message -->
                  <p class="truncate text-body font-medium text-ink">
                    {{ item.title }}
                  </p>
                </div>
                <Icon
                  name="ArrowRight"
                  :size="14"
                  class="mt-1 shrink-0 text-ink-faint transition group-hover:text-ink-muted"
                />
              </NuxtLink>
            </li>
          </ul>

          <p
            v-else
            class="py-8 text-center text-caption text-ink-muted"
          >
            {{ t("owner.dashboard.attention.empty") }}
          </p>
        </Card>
      </section>
    </template>
  </div>
</template>
