<script setup lang="ts">
import { computed, h, onMounted, ref, watch } from "vue";
import { FlexRender, getCoreRowModel, useVueTable, type ColumnDef } from "@tanstack/vue-table";
import Card from "~/components/ui/Card.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";
import DataTableShell from "~/components/admin/DataTableShell.vue";
import OwnerStatusPill from "~/components/admin/OwnerStatusPill.vue";
import type { AdminOwner, OwnerListQuery, OwnerStatus, Paginated, PlanTier } from "~/types/admin";

definePageMeta({ layout: "admin" });
const { t } = useI18n();
useHead({ title: () => t("admin.nav.owners") });

const route = useRoute();
const router = useRouter();

const q = ref(String(route.query.q ?? ""));
const plan = ref<PlanTier | "all">((route.query.plan as PlanTier) ?? "all");
const status = ref<OwnerStatus | "all">((route.query.status as OwnerStatus) ?? "all");
const overCap = ref(route.query.overCap === "1");
const overdue = ref(route.query.overdue === "1");
const page = ref(Number(route.query.page ?? 1));

const loading = ref(true);
const result = ref<Paginated<AdminOwner>>({ data: [], meta: { page: 1, perPage: 20, total: 0, lastPage: 1 } });

const query = computed<OwnerListQuery>(() => ({
  q: q.value || undefined,
  plan: plan.value === "all" ? undefined : plan.value,
  status: status.value === "all" ? undefined : status.value,
  overCap: overCap.value || undefined,
  overdue: overdue.value || undefined,
  page: page.value,
}));

let debounce: ReturnType<typeof setTimeout> | null = null;
const load = async () => {
  loading.value = true;
  try {
    result.value = await useAdminOwners().list(query.value);
    router.replace({
      query: Object.fromEntries(
        Object.entries({
          ...query.value,
          page: page.value > 1 ? String(page.value) : undefined,
          overCap: overCap.value ? "1" : undefined,
          overdue: overdue.value ? "1" : undefined,
        }).filter(([, v]) => v !== undefined),
      ) as Record<string, string>,
    });
  } finally {
    loading.value = false;
  }
};
onMounted(load);
watch([plan, status, overCap, overdue], () => {
  if (page.value !== 1) page.value = 1; // watch(page) will load
  else load();
});
watch(page, load);
watch(q, () => {
  if (debounce) clearTimeout(debounce);
  debounce = setTimeout(() => {
    if (page.value !== 1) page.value = 1; // watch(page) will load
    else load();
  }, 300);
});

const filtersActive = computed(() => q.value !== "" || plan.value !== "all" || status.value !== "all" || overCap.value || overdue.value);
const clearFilters = () => { q.value = ""; plan.value = "all"; status.value = "all"; overCap.value = false; overdue.value = false; };

const planOptions = computed(() => [
  { value: "all", label: t("admin.common.all") },
  ...(["free", "starter", "pro", "business"] as PlanTier[]).map((p) => ({ value: p, label: t(`admin.plan.${p}`) })),
]);
const statusOptions = computed(() => [
  { value: "all", label: t("admin.common.all") },
  { value: "active", label: t("admin.status.owner.active") },
  { value: "suspended", label: t("admin.status.owner.suspended") },
]);

const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString("en-MY", { day: "2-digit", month: "short", year: "numeric" }) : t("admin.common.never"));
const capLabel = (o: AdminOwner) => `${o.unitsUsed} / ${o.unitsCap ?? "∞"}`;
const open = (o: AdminOwner) => router.push(`/admin/owners/${o.id}`);

const columns = computed<ColumnDef<AdminOwner>[]>(() => [
  { id: "name", header: () => t("admin.owners.columns.owner"), cell: (i) => h("div", { class: "min-w-0" }, [
      h("div", { class: "truncate text-body text-ink" }, i.row.original.name),
      h("div", { class: "truncate text-caption text-ink-muted" }, i.row.original.email),
      ...(i.row.original.businessName ? [h("div", { class: "truncate text-micro text-ink-faint" }, i.row.original.businessName)] : []),
    ]) },
  { id: "plan", header: () => t("admin.owners.columns.plan"), cell: (i) => h("span", { class: "text-caption" }, t(`admin.plan.${i.row.original.planTier}`)) },
  { id: "units", header: () => t("admin.owners.columns.units"), cell: (i) => h("span", {
      class: ["text-caption tabular-nums", i.row.original.unitsCap !== null && i.row.original.unitsUsed > i.row.original.unitsCap ? "text-status-overdue" : ""],
    }, capLabel(i.row.original)) },
  { id: "properties", header: () => t("admin.owners.columns.properties"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, String(i.row.original.counts.properties)) },
  { id: "tenants", header: () => t("admin.owners.columns.tenants"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, String(i.row.original.counts.tenants)) },
  { id: "overdue", header: () => t("admin.owners.columns.overdue"), cell: (i) => h("span", { class: ["text-caption tabular-nums", i.row.original.counts.invoicesOverdue > 0 ? "text-status-overdue" : ""] }, String(i.row.original.counts.invoicesOverdue)) },
  { id: "status", header: () => t("admin.owners.columns.status"), cell: (i) => h(OwnerStatusPill, { status: i.row.original.status }) },
  { id: "signedUp", header: () => t("admin.common.signedUp"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.createdAt)) },
  { id: "lastActive", header: () => t("admin.common.lastActive"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.lastActiveAt)) },
]);

const table = useVueTable({
  get data() { return result.value.data; },
  get columns() { return columns.value; },
  getCoreRowModel: getCoreRowModel(),
  manualPagination: true,
});
</script>

<template>
  <div>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.owners.title") }}</h1>
      <p class="mt-2 text-caption text-ink-muted">{{ t("admin.owners.subtitle") }}</p>
    </header>

    <Card padding="compact" class="mb-4 sm:mb-6">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <Input v-model="q" :placeholder="t('admin.owners.searchPlaceholder')" class="lg:col-span-2" />
        <Select v-model="plan" :options="planOptions" />
        <Select v-model="status" :options="statusOptions" />
        <div class="flex items-center gap-4 text-caption text-ink-strong">
          <label class="inline-flex items-center gap-2"><input v-model="overCap" type="checkbox" class="accent-admin" />{{ t("admin.owners.filters.overCap") }}</label>
          <label class="inline-flex items-center gap-2"><input v-model="overdue" type="checkbox" class="accent-admin" />{{ t("admin.owners.filters.overdue") }}</label>
        </div>
      </div>
      <div v-if="filtersActive" class="mt-3">
        <Button variant="ghost" size="sm" @click="clearFilters">{{ t("admin.common.clearFilters") }}</Button>
      </div>
    </Card>

    <DataTableShell
      :loading="loading"
      :empty="result.data.length === 0"
      :empty-title="t('admin.common.noResults')"
      :empty-description="t('admin.common.noResultsHelp')"
      :page="result.meta.page"
      :last-page="result.meta.lastPage"
      :total="result.meta.total"
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
              @click="open(row.original)"
              @keydown.enter.prevent="open(row.original)"
            >
              <td v-for="cell in row.getVisibleCells()" :key="cell.id" class="px-3 py-3 align-top">
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </td>
            </tr>
          </tbody>
        </table>
      </template>
      <template #cards>
        <Card v-for="o in result.data" :key="o.id" padding="compact">
          <button type="button" class="block w-full text-left rounded-lg outline-none focus-visible:shadow-focus" @click="open(o)">
            <div class="flex items-center gap-2">
              <OwnerStatusPill :status="o.status" />
              <span class="text-micro text-ink-faint">{{ t(`admin.plan.${o.planTier}`) }} · {{ capLabel(o) }}</span>
            </div>
            <p class="mt-1 text-body font-medium text-ink">{{ o.name }}</p>
            <p class="text-caption text-ink-muted">{{ o.email }}</p>
            <p v-if="o.businessName" class="text-micro text-ink-faint">{{ o.businessName }}</p>
            <p class="mt-1 text-micro text-ink-faint">
              {{ t("admin.owners.columns.properties") }} {{ o.counts.properties }} · {{ t("admin.owners.columns.tenants") }} {{ o.counts.tenants }} · {{ t("admin.owners.columns.overdue") }} {{ o.counts.invoicesOverdue }}
            </p>
          </button>
        </Card>
      </template>
    </DataTableShell>
  </div>
</template>
