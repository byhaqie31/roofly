<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";
import Select from "~/components/ui/Select.vue";
import MoneyDisplay from "~/components/ui/MoneyDisplay.vue";
import MiniBarChart from "~/components/ui/MiniBarChart.vue";
import Icon from "~/components/ui/Icon.vue";
import { useReports } from "~/composables/useReports";
import { useToast } from "~/composables/useToast";
import { downloadCsv } from "~/utils/csv";

definePageMeta({ layout: "owner" });

const { t } = useI18n();
const { show } = useToast();
const { formatRM } = useMoney();

useHead({ title: () => t("owner.nav.reports") });

const year = ref(new Date().getFullYear());
const reports = useReports(year);

onMounted(async () => {
  await reports.load();
  // Default the picker to the most recent year that actually has data.
  const years = reports.availableYears.value;
  if (years.length > 0 && !years.includes(year.value)) {
    year.value = years[0]!;
  }
});

const yearOptions = computed(() =>
  reports.availableYears.value.map((y) => ({
    value: String(y),
    label: String(y),
  })),
);

const yearString = computed({
  get: () => String(year.value),
  set: (v: string) => {
    year.value = Number(v);
  },
});

const onDownloadCsv = () => {
  const rows = reports.perProperty.value.map((row) => [
    row.property.name,
    row.property.type,
    row.property.city,
    row.unitsCount,
    row.occupiedCount,
    `${row.occupancyPct}%`,
    (row.incomeForYear / 100).toFixed(2),
    (row.outstanding / 100).toFixed(2),
    row.gains ? (row.gains.net / 100).toFixed(2) : "",
  ]);
  downloadCsv(
    `roofly-report-${year.value}.csv`,
    [
      t("owner.reports.csv.property"),
      t("owner.reports.csv.type"),
      t("owner.reports.csv.city"),
      t("owner.reports.csv.units"),
      t("owner.reports.csv.occupied"),
      t("owner.reports.csv.occupancy"),
      t("owner.reports.csv.income"),
      t("owner.reports.csv.outstanding"),
      t("owner.reports.csv.netGain"),
    ],
    rows,
  );
  show(t("owner.reports.csvDownloaded"), "success");
};

const onDownloadPdf = () => {
  show(t("owner.reports.pdfStub"), "default");
};
</script>

<template>
  <div>
    <header class="mb-6">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.nav.reports") }}
      </h1>
      <p class="mt-2 text-caption text-ink-muted">
        {{ t("owner.reports.subtitle") }}
      </p>
      <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <div class="w-28 [&_button]:bg-surface-raised">
          <Select v-model="yearString" :options="yearOptions" />
        </div>
        <div class="flex items-center gap-2">
          <Button
            variant="ghost"
            size="md"
            :disabled="reports.isEmpty.value"
            @click="onDownloadCsv"
          >
            <Icon name="Download" :size="14" class="mr-1.5" />
            {{ t("owner.reports.csvDownload") }}
          </Button>
          <Button
            variant="ghost"
            size="md"
            @click="onDownloadPdf"
          >
            <Icon name="FileText" :size="14" class="mr-1.5" />
            {{ t("owner.reports.pdfDownload") }}
          </Button>
        </div>
      </div>
    </header>

    <Card v-if="reports.loading.value" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("common.loading") }}
      </p>
    </Card>

    <Card v-else-if="reports.isEmpty.value" padding="loose">
      <EmptyState
        icon="ChartBar"
        :title="t('owner.reports.empty.title')"
        :description="t('owner.reports.empty.description')"
      />
    </Card>

    <template v-else>
      <section class="mb-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
        <Card padding="standard">
          <p class="text-caption text-ink-muted">
            {{ t("owner.reports.totalIncome", { year }) }}
          </p>
          <p class="mt-2 text-display-sub font-semibold tracking-snug">
            <MoneyDisplay :cents="reports.totalIncome.value" emphasis />
          </p>
          <p class="mt-1 text-micro text-ink-faint">
            {{ t("owner.reports.totalIncomeHelp") }}
          </p>
        </Card>
        <Card padding="standard">
          <p class="text-caption text-ink-muted">
            {{ t("owner.reports.totalOutstanding") }}
          </p>
          <p class="mt-2 text-display-sub font-semibold tracking-snug">
            <MoneyDisplay :cents="reports.totalOutstanding.value" emphasis />
          </p>
          <p class="mt-1 text-micro text-ink-faint">
            {{ t("owner.reports.totalOutstandingHelp") }}
          </p>
        </Card>
      </section>

      <section class="mb-6">
        <Card padding="loose">
          <header class="mb-4">
            <h2 class="text-card-title font-semibold text-ink">
              {{ t("owner.reports.monthlyBreakdown.title", { year }) }}
            </h2>
            <p class="mt-1 text-caption text-ink-muted">
              {{ t("owner.reports.monthlyBreakdown.help") }}
            </p>
          </header>
          <MiniBarChart
            :data="reports.monthlyBreakdown.value"
            :height="120"
            :highlight-last="false"
          />
        </Card>
      </section>

      <section>
        <div class="overflow-hidden rounded-lg border border-line-passive bg-surface-raised">
          <header class="border-b border-line-passive p-6">
            <h2 class="text-card-title font-semibold text-ink">
              {{ t("owner.reports.perProperty.title") }}
            </h2>
            <p class="mt-1 text-caption text-ink-muted">
              {{ t("owner.reports.perProperty.help") }}
            </p>
          </header>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="border-b border-line-passive bg-surface-page">
                <tr class="text-left">
                  <th class="px-4 py-3 text-caption font-medium text-ink-muted">
                    {{ t("owner.reports.perProperty.cols.name") }}
                  </th>
                  <th class="px-4 py-3 text-caption font-medium text-ink-muted">
                    {{ t("owner.reports.perProperty.cols.units") }}
                  </th>
                  <th class="px-4 py-3 text-caption font-medium text-ink-muted">
                    {{ t("owner.reports.perProperty.cols.occupancy") }}
                  </th>
                  <th class="px-4 py-3 text-right text-caption font-medium text-ink-muted">
                    {{ t("owner.reports.perProperty.cols.income") }}
                  </th>
                  <th class="px-4 py-3 text-right text-caption font-medium text-ink-muted">
                    {{ t("owner.reports.perProperty.cols.outstanding") }}
                  </th>
                  <th class="px-4 py-3 text-right text-caption font-medium text-ink-muted">
                    {{ t("owner.reports.perProperty.cols.netGain") }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in reports.perProperty.value"
                  :key="row.property.id"
                  class="border-b border-line-passive last:border-b-0 transition hover:bg-surface-hover/40"
                >
                  <td class="px-4 py-3">
                    <NuxtLink
                      :to="`/owner/properties/${row.property.id}`"
                      class="text-body text-ink underline-offset-2 hover:underline"
                    >
                      {{ row.property.name }}
                    </NuxtLink>
                    <p class="text-micro text-ink-faint">
                      {{ t(`owner.properties.types.${row.property.type}`) }} ·
                      {{ row.property.city }}
                    </p>
                  </td>
                  <td class="px-4 py-3 text-body text-ink num">
                    {{ row.occupiedCount }}/{{ row.unitsCount }}
                  </td>
                  <td class="px-4 py-3 text-body text-ink num">
                    {{ row.occupancyPct }}%
                  </td>
                  <td class="px-4 py-3 text-right">
                    <MoneyDisplay :cents="row.incomeForYear" />
                  </td>
                  <td class="px-4 py-3 text-right">
                    <MoneyDisplay :cents="row.outstanding" />
                  </td>
                  <td class="px-4 py-3 text-right">
                    <span v-if="row.gains" class="num text-ink">
                      {{ formatRM(row.gains.net) }}
                    </span>
                    <span v-else class="text-ink-faint">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <footer class="border-t border-line-passive p-4 text-micro text-ink-faint">
            {{ t("owner.reports.perProperty.disclaimer") }}
          </footer>
        </div>
      </section>
    </template>
  </div>
</template>
