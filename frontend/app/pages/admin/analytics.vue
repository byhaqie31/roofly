<script setup lang="ts">
import { computed, h, onMounted, ref, watch } from "vue";
import { FlexRender, getCoreRowModel, useVueTable, type ColumnDef } from "@tanstack/vue-table";
import Card from "~/components/ui/Card.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";
import MiniAreaChart from "~/components/ui/MiniAreaChart.vue";
import StatTile from "~/components/admin/StatTile.vue";
import DataTableShell from "~/components/admin/DataTableShell.vue";
import SourcePill from "~/components/admin/SourcePill.vue";
import FunnelStrip from "~/components/admin/FunnelStrip.vue";
import LeadDrawer from "~/components/admin/LeadDrawer.vue";
import NoAccess from "~/components/admin/NoAccess.vue";
import { downloadCsvText } from "~/utils/csv";
import { formatAdminDate } from "~/utils/adminDate";
import type { AdminLead, AnalyticsOverview, AnalyticsRange, LeadListQuery, LeadSource } from "~/types/analytics";
import type { Paginated } from "~/types/admin";

definePageMeta({ layout: "admin" });
const { can } = useAdminPermissions();
const { t } = useI18n();
useHead({ title: () => t("admin.nav.analytics") });

const route = useRoute();
const router = useRouter();

// ── Range ──────────────────────────────────────────────────────────────
type Preset = "d7" | "d30" | "d90" | "custom";
const PRESET_DAYS: Record<Exclude<Preset, "custom">, number> = { d7: 7, d30: 30, d90: 90 };

const preset = ref<Preset>((route.query.preset as Preset) ?? "d30");
const from = ref(String(route.query.from ?? ""));
const to = ref(String(route.query.to ?? ""));

const isoDate = (d: Date) => d.toISOString().slice(0, 10);

const range = computed<AnalyticsRange>(() => {
  if (preset.value === "custom") {
    return { from: from.value || undefined, to: to.value || undefined };
  }
  const toDate = new Date();
  const fromDate = new Date();
  fromDate.setDate(fromDate.getDate() - (PRESET_DAYS[preset.value] - 1));
  return { from: isoDate(fromDate), to: isoDate(toDate) };
});

const presetOptions = computed(() => [
  { value: "d7", label: t("admin.analytics.range.d7") },
  { value: "d30", label: t("admin.analytics.range.d30") },
  { value: "d90", label: t("admin.analytics.range.d90") },
  { value: "custom", label: t("admin.analytics.range.custom") },
]);

const MAX_CUSTOM_RANGE_DAYS = 366;
const rangeError = ref<string | null>(null);
const validCustomRange = computed(() => {
  if (!from.value || !to.value) return false;
  if (from.value > to.value) return false; // ISO yyyy-mm-dd strings compare lexicographically
  const spanDays = (Date.parse(to.value) - Date.parse(from.value)) / 86_400_000;
  return spanDays + 1 <= MAX_CUSTOM_RANGE_DAYS; // inclusive day count, matches the backend's from..to bounds
});

// ── Overview ───────────────────────────────────────────────────────────
const overview = ref<AnalyticsOverview | null>(null);
const loading = ref(true);

const count = (n: number) => String(n);

const loadOverview = async () => {
  loading.value = true;
  try {
    overview.value = await useAdminAnalytics().overview(range.value);
    syncRoute();
  } catch {
    useToast().show(t("common.genericError"), "danger");
  } finally {
    loading.value = false;
  }
};

const applyCustomRange = () => {
  if (validCustomRange.value) {
    rangeError.value = null;
    loadOverview();
  } else {
    rangeError.value = from.value && to.value ? t("admin.analytics.range.invalid") : null;
  }
};

const viewsSeries = computed(() => {
  if (!overview.value) return [];
  const { days, views } = overview.value.series;
  return days.map((day, i) => ({
    key: day,
    label: new Date(day).toLocaleDateString("en-MY", { day: "2-digit", month: "short" }),
    amount: views[i] ?? 0,
  }));
});
const registrationsSeries = computed(() => {
  if (!overview.value) return [];
  const { days, registrations } = overview.value.series;
  return days.map((day, i) => ({
    key: day,
    label: new Date(day).toLocaleDateString("en-MY", { day: "2-digit", month: "short" }),
    amount: registrations[i] ?? 0,
  }));
});

const funnelSteps = computed(() => {
  if (!overview.value) return [];
  const f = overview.value.funnel;
  return [
    { key: "visitors", label: t("admin.analytics.funnel.visitors"), count: f.visitors },
    { key: "demo", label: t("admin.analytics.funnel.demo"), count: f.demo },
    { key: "leads", label: t("admin.analytics.funnel.leads"), count: f.leads },
    { key: "registered", label: t("admin.analytics.funnel.registered"), count: f.registered },
  ];
});

const referrerLabel = (referrer: string) => (referrer === "direct" ? t("admin.analytics.direct") : referrer);

// ── Leads ──────────────────────────────────────────────────────────────
const q = ref(String(route.query.q ?? ""));
const source = ref<LeadSource | "all">((route.query.source as LeadSource) ?? "all");
const converted = ref(route.query.converted === "1");
const page = ref(Number(route.query.page ?? 1));

const leadsLoading = ref(true);
const leadsResult = ref<Paginated<AdminLead>>({ data: [], meta: { page: 1, perPage: 20, total: 0, lastPage: 1 } });

const leadsQuery = computed<LeadListQuery>(() => ({
  q: q.value || undefined,
  source: source.value === "all" ? undefined : source.value,
  converted: converted.value || undefined,
  page: page.value,
}));

const loadLeads = async () => {
  leadsLoading.value = true;
  try {
    leadsResult.value = await useAdminAnalytics().leads(leadsQuery.value);
  } finally {
    leadsLoading.value = false;
  }
  syncRoute();
};

function syncRoute() {
  router.replace({
    query: Object.fromEntries(
      Object.entries({
        preset: preset.value !== "d30" ? preset.value : undefined,
        from: preset.value === "custom" ? from.value || undefined : undefined,
        to: preset.value === "custom" ? to.value || undefined : undefined,
        q: q.value || undefined,
        source: source.value === "all" ? undefined : source.value,
        converted: converted.value ? "1" : undefined,
        page: page.value > 1 ? String(page.value) : undefined,
      }).filter(([, v]) => v !== undefined),
    ) as Record<string, string>,
  });
}

onMounted(() => {
  if (can("analytics.view")) {
    loadOverview();
    loadLeads();
  }
});

watch(preset, () => {
  if (preset.value !== "custom") {
    from.value = "";
    to.value = "";
    rangeError.value = null;
    loadOverview();
    return;
  }
  applyCustomRange();
});
watch([from, to], () => {
  if (preset.value === "custom") applyCustomRange();
});

watch([source, converted], () => {
  if (page.value !== 1) page.value = 1; // watch(page) will load
  else loadLeads();
});
watch(page, loadLeads);

let debounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (debounce) clearTimeout(debounce);
  debounce = setTimeout(() => {
    if (page.value !== 1) page.value = 1; // watch(page) will load
    else loadLeads();
  }, 300);
});

// No "demo" filter option: a lead is created from an email a person typed (waitlist signup or
// register), and demo_enter carries no email, so no lead can ever have source "demo". The
// LeadSource type and backend enum value are kept for forward compatibility.
const sourceOptions = computed(() => [
  { value: "all", label: t("admin.common.all") },
  { value: "waitlist", label: t("admin.analytics.sources.waitlist") },
  { value: "register", label: t("admin.analytics.sources.register") },
]);

const fmtDate = formatAdminDate;

// ── Lead drawer ────────────────────────────────────────────────────────
const drawerOpen = ref(false);
const selectedLeadId = ref<string | null>(null);
const openLead = (lead: AdminLead) => {
  selectedLeadId.value = lead.id;
  drawerOpen.value = true;
};

const goToOwner = (id: string, e: MouseEvent) => {
  e.stopPropagation();
  e.preventDefault();
  router.push(`/admin/owners/${id}`);
};

const columns = computed<ColumnDef<AdminLead>[]>(() => [
  { id: "email", header: () => t("admin.analytics.leads.columns.email"), cell: (i) => h("span", { class: "text-body text-ink" }, i.row.original.email) },
  { id: "source", header: () => t("admin.analytics.leads.columns.source"), cell: (i) => h(SourcePill, { source: i.row.original.source }) },
  { id: "firstSeen", header: () => t("admin.analytics.leads.columns.firstSeen"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.firstSeenAt)) },
  { id: "lastSeen", header: () => t("admin.analytics.leads.columns.lastSeen"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.lastSeenAt)) },
  { id: "views", header: () => t("admin.analytics.leads.columns.views"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, String(i.row.original.pageViews)) },
  { id: "demo", header: () => t("admin.analytics.leads.columns.demo"), cell: (i) => h("span", { class: "text-caption" }, i.row.original.demoEntered ? "✓" : "—") },
  {
    id: "converted",
    header: () => t("admin.analytics.leads.columns.converted"),
    cell: (i) => {
      const lead = i.row.original;
      const ownerId = lead.convertedUserId;
      if (!ownerId) return h("span", { class: "text-caption text-ink-faint" }, "—");
      return h(
        "a",
        {
          href: `/admin/owners/${ownerId}`,
          class: "text-ink underline underline-offset-2",
          onClick: (e: MouseEvent) => goToOwner(ownerId, e),
          onKeydown: (e: KeyboardEvent) => e.stopPropagation(),
        },
        lead.convertedOwnerName ?? "",
      );
    },
  },
]);

const table = useVueTable({
  get data() { return leadsResult.value.data; },
  get columns() { return columns.value; },
  getCoreRowModel: getCoreRowModel(),
  manualPagination: true,
});

const exporting = ref(false);
const exportCsv = async () => {
  exporting.value = true;
  try {
    const csv = await useAdminAnalytics().exportCsv({ ...leadsQuery.value, page: undefined });
    downloadCsvText(`roofly-leads-${new Date().toISOString().slice(0, 10)}.csv`, csv);
  } catch {
    useToast().show(t("common.genericError"), "danger");
  } finally {
    exporting.value = false;
  }
};
</script>

<template>
  <NoAccess v-if="!can('analytics.view')" permission="analytics.view" />
  <div v-else>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.analytics.title") }}</h1>
      <p class="mt-2 text-caption text-ink-muted">{{ t("admin.analytics.subtitle") }}</p>
    </header>

    <Card padding="compact" class="mb-4 sm:mb-6">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <Select v-model="preset" :options="presetOptions" :label="t('admin.analytics.range.label')" />
        <Input v-if="preset === 'custom'" v-model="from" type="date" :label="t('admin.analytics.range.from')" />
        <Input v-if="preset === 'custom'" v-model="to" type="date" :label="t('admin.analytics.range.to')" />
      </div>
      <p v-if="rangeError" class="mt-3 text-caption text-accent" role="alert">{{ rangeError }}</p>
    </Card>

    <Card v-if="loading" padding="loose" class="mb-6 sm:mb-8">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <template v-else-if="overview">
      <section class="mb-6 grid grid-cols-1 gap-4 sm:mb-8 sm:grid-cols-2 sm:gap-6 xl:grid-cols-3 2xl:grid-cols-6">
        <StatTile :label="t('admin.analytics.tiles.views')" :value="overview.tiles.views" />
        <StatTile
          :label="t('admin.analytics.tiles.visitors')"
          :value="overview.tiles.visitors"
          :help="t('admin.analytics.tiles.visitorsHelp', { n: overview.tiles.newVisitors })"
        />
        <StatTile :label="t('admin.analytics.tiles.demo')" :value="overview.tiles.demoEntries" />
        <StatTile :label="t('admin.analytics.tiles.leads')" :value="overview.tiles.leads" />
        <StatTile :label="t('admin.analytics.tiles.registrations')" :value="overview.tiles.registrations" />
        <StatTile
          :label="t('admin.analytics.tiles.conversion')"
          :value="`${overview.tiles.conversionPct}%`"
          :help="t('admin.analytics.tiles.conversionHelp')"
        />
      </section>

      <section class="mb-6 grid grid-cols-1 gap-4 sm:mb-8 sm:gap-6 lg:grid-cols-2">
        <Card padding="loose">
          <h2 class="text-card-title font-semibold text-ink">{{ t("admin.analytics.charts.views") }}</h2>
          <MiniAreaChart class="mt-4" :data="viewsSeries" :height="120" :format="count" :show-average-line="true" />
        </Card>
        <Card padding="loose">
          <h2 class="text-card-title font-semibold text-ink">{{ t("admin.analytics.charts.registrations") }}</h2>
          <MiniAreaChart class="mt-4" :data="registrationsSeries" :height="120" :format="count" :show-average-line="true" />
        </Card>
      </section>

      <section class="mb-6 sm:mb-8">
        <h2 class="mb-3 text-card-title font-semibold text-ink">{{ t("admin.analytics.funnel.title") }}</h2>
        <FunnelStrip :steps="funnelSteps" />
      </section>

      <section class="mb-6 grid grid-cols-1 gap-4 sm:mb-8 sm:gap-6 lg:grid-cols-2">
        <Card padding="loose">
          <h2 class="text-card-title font-semibold text-ink">{{ t("admin.analytics.topPages") }}</h2>
          <ul class="mt-4 space-y-2">
            <li v-for="p in overview.topPages" :key="p.path" class="flex items-center justify-between gap-3 text-caption">
              <span class="truncate text-ink">{{ p.path }}</span>
              <span class="tabular-nums text-ink-muted">{{ p.views }}</span>
            </li>
          </ul>
        </Card>
        <Card padding="loose">
          <h2 class="text-card-title font-semibold text-ink">{{ t("admin.analytics.referrers") }}</h2>
          <ul class="mt-4 space-y-2">
            <li v-for="r in overview.referrers" :key="r.referrer" class="flex items-center justify-between gap-3 text-caption">
              <span class="truncate text-ink">{{ referrerLabel(r.referrer) }}</span>
              <span class="tabular-nums text-ink-muted">{{ r.visitors }}</span>
            </li>
          </ul>
        </Card>
      </section>
    </template>

    <section>
      <header class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <h2 class="text-card-title font-semibold text-ink">{{ t("admin.analytics.leads.title") }}</h2>
        <Button variant="ghost" size="sm" class="self-start" :loading="exporting" @click="exportCsv">{{ t("admin.common.exportCsv") }}</Button>
      </header>

      <Card padding="compact" class="mb-4 sm:mb-6">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Input v-model="q" :placeholder="t('admin.analytics.leads.searchPlaceholder')" class="lg:col-span-2" />
          <Select v-model="source" :options="sourceOptions" />
          <label class="inline-flex items-center gap-2 text-caption text-ink-strong">
            <input v-model="converted" type="checkbox" class="accent-admin" />{{ t("admin.analytics.leads.filters.converted") }}
          </label>
        </div>
      </Card>

      <DataTableShell
        :loading="leadsLoading"
        :empty="leadsResult.data.length === 0"
        :empty-title="t('admin.analytics.leads.empty')"
        :empty-description="t('admin.analytics.leads.emptyHelp')"
        :page="leadsResult.meta.page"
        :last-page="leadsResult.meta.lastPage"
        :total="leadsResult.meta.total"
        @update:page="page = $event"
      >
        <template #table>
          <table class="w-full text-left">
            <thead>
              <tr v-for="hg in table.getHeaderGroups()" :key="hg.id" class="border-b border-line-passive">
                <th v-for="hd in hg.headers" :key="hd.id" class="px-3 py-2 text-micro font-medium uppercase tracking-wider text-ink-muted">
                  <FlexRender :render="hd.column.columnDef.header" :props="hd.getContext()" />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in table.getRowModel().rows"
                :key="row.id"
                tabindex="0"
                role="link"
                class="border-b border-line-passive last:border-0 cursor-pointer outline-none hover:bg-surface-hover focus-visible:shadow-focus"
                @click="openLead(row.original)"
                @keydown.enter.prevent="openLead(row.original)"
              >
                <td v-for="cell in row.getVisibleCells()" :key="cell.id" class="px-3 py-3 align-top">
                  <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                </td>
              </tr>
            </tbody>
          </table>
        </template>
        <template #cards>
          <Card v-for="lead in leadsResult.data" :key="lead.id" padding="compact">
            <button type="button" class="block w-full text-left rounded-lg outline-none focus-visible:shadow-focus" @click="openLead(lead)">
              <div class="flex items-center gap-2">
                <SourcePill :source="lead.source" />
                <span class="text-micro text-ink-faint">{{ t("admin.analytics.leads.columns.views") }} {{ lead.pageViews }}</span>
              </div>
              <p class="mt-1 text-body font-medium text-ink">{{ lead.email }}</p>
              <p class="text-caption text-ink-muted">{{ fmtDate(lead.firstSeenAt) }} &ndash; {{ fmtDate(lead.lastSeenAt) }}</p>
              <p class="mt-1 text-micro text-ink-faint">
                {{ t("admin.analytics.leads.columns.demo") }} {{ lead.demoEntered ? "✓" : "—" }} ·
                <span class="text-caption text-ink-muted">{{ lead.convertedOwnerName ?? "—" }}</span>
              </p>
            </button>
          </Card>
        </template>
      </DataTableShell>
    </section>

    <LeadDrawer v-model:open="drawerOpen" :lead-id="selectedLeadId" />
  </div>
</template>
