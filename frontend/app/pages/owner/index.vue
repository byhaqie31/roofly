<script setup lang="ts">
import { computed, onMounted } from "vue";
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";
import MoneyDisplay from "~/components/ui/MoneyDisplay.vue";
import MiniBarChart from "~/components/ui/MiniBarChart.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import { useDashboard, type AttentionKind } from "~/composables/useDashboard";

definePageMeta({ layout: "owner" });

const { t } = useI18n();
useHead({ title: () => t("owner.dashboard.title") });

const dashboard = useDashboard();
onMounted(() => dashboard.load());

const occupiedCount = computed(
  () => dashboard.units.value.filter((u) => u.status === "occupied").length,
);
const outstandingCount = computed(
  () =>
    dashboard.invoices.value.filter(
      (i) => i.status === "pending" || i.status === "overdue",
    ).length,
);

const attentionTone: Record<AttentionKind, "overdue" | "maintenance" | "draft"> = {
  overdue: "overdue",
  expiring: "maintenance",
  notice_given: "draft",
};
</script>

<template>
  <div>
    <header class="mb-8">
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
      <section class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
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

      <section class="mb-8">
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
          <MiniBarChart :data="dashboard.monthlyIncomeSeries.value" :height="120" />
        </Card>
      </section>

      <section>
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
                class="group flex items-center justify-between gap-3 py-3 outline-none transition hover:bg-surface-hover/50 focus-visible:shadow-focus"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <Pill :tone="attentionTone[item.kind]">
                    {{ t(`owner.dashboard.attention.kinds.${item.kind}`) }}
                  </Pill>
                  <span class="truncate text-body text-ink">
                    {{ item.title }}
                  </span>
                  <span
                    v-if="item.meta"
                    class="truncate text-caption text-ink-muted"
                  >
                    {{ item.meta }}
                  </span>
                </div>
                <Icon
                  name="ArrowRight"
                  :size="14"
                  class="shrink-0 text-ink-faint transition group-hover:text-ink-muted"
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
