<script setup lang="ts">
import { computed, h, onMounted, ref, watch } from "vue";
import { FlexRender, getCoreRowModel, useVueTable, type ColumnDef } from "@tanstack/vue-table";
import Card from "~/components/ui/Card.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Icon from "~/components/ui/Icon.vue";
import Pill from "~/components/ui/Pill.vue";
import DataTableShell from "~/components/admin/DataTableShell.vue";
import type { AdminTenant, Paginated, TenantListQuery, TenantStatus } from "~/types/admin";

definePageMeta({ layout: "admin" });
const { t } = useI18n();
useHead({ title: () => t("admin.nav.tenants") });

const route = useRoute();
const router = useRouter();

const q = ref(String(route.query.q ?? ""));
const status = ref<TenantStatus | "all">((route.query.status as TenantStatus) ?? "all");
const ownerId = ref(String(route.query.ownerId ?? ""));
const page = ref(Number(route.query.page ?? 1));

const loading = ref(true);
const result = ref<Paginated<AdminTenant>>({ data: [], meta: { page: 1, perPage: 20, total: 0, lastPage: 1 } });

const query = computed<TenantListQuery>(() => ({
  q: q.value || undefined,
  status: status.value === "all" ? undefined : status.value,
  ownerId: ownerId.value || undefined,
  page: page.value,
}));

let debounce: ReturnType<typeof setTimeout> | null = null;
const load = async () => {
  loading.value = true;
  try {
    result.value = await useAdminTenants().list(query.value);
    router.replace({
      query: Object.fromEntries(
        Object.entries({
          ...query.value,
          page: page.value > 1 ? String(page.value) : undefined,
        }).filter(([, v]) => v !== undefined),
      ) as Record<string, string>,
    });
  } finally {
    loading.value = false;
  }
};
onMounted(load);
watch([status, ownerId], () => {
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

const clearOwnerFilter = () => {
  ownerId.value = "";
};

const statusOptions = computed(() => [
  { value: "all", label: t("admin.common.all") },
  ...(["invited", "active", "notice_given", "moved_out"] as TenantStatus[]).map((s) => ({ value: s, label: t(`admin.status.tenant.${s}`) })),
]);

const tenantTone = (s: TenantStatus) => (s === "invited" ? "draft" : s === "active" ? "active" : s === "notice_given" ? "maintenance" : "expired");
const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString("en-MY", { day: "2-digit", month: "short", year: "numeric" }) : t("admin.common.never"));
const open = (tn: AdminTenant) => router.push(`/admin/tenants/${tn.id}`);

const columns = computed<ColumnDef<AdminTenant>[]>(() => [
  { id: "name", header: () => t("admin.tenants.columns.tenant"), cell: (i) => h("div", { class: "min-w-0" }, [
      h("div", { class: "truncate text-body text-ink" }, i.row.original.name),
      h("div", { class: "truncate text-caption text-ink-muted" }, i.row.original.email),
    ]) },
  { id: "phone", header: () => t("admin.tenants.columns.phone"), cell: (i) => h("span", { class: "text-caption" }, i.row.original.phone ?? "—") },
  { id: "status", header: () => t("admin.tenants.columns.status"), cell: (i) => h(Pill, { tone: tenantTone(i.row.original.status) }, () => t(`admin.status.tenant.${i.row.original.status}`)) },
  { id: "owner", header: () => t("admin.tenants.columns.owner"), cell: (i) => h("span", { class: "text-caption" }, i.row.original.ownerName ?? "—") },
  { id: "propertyUnit", header: () => t("admin.tenants.columns.propertyUnit"), cell: (i) => h("span", { class: "text-caption" }, `${i.row.original.propertyName ?? "—"} · ${i.row.original.unitLabel ?? "—"}`) },
  { id: "invited", header: () => t("admin.tenants.columns.invited"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.invitedAt)) },
  { id: "accepted", header: () => t("admin.tenants.columns.accepted"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.acceptedAt)) },
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
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.tenants.title") }}</h1>
      <p class="mt-2 text-caption text-ink-muted">{{ t("admin.tenants.subtitle") }}</p>
    </header>

    <Card padding="compact" class="mb-4 sm:mb-6">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <Input v-model="q" :placeholder="t('admin.tenants.searchPlaceholder')" class="lg:col-span-2" />
        <Select v-model="status" :options="statusOptions" />
      </div>
      <div v-if="ownerId" class="mt-3">
        <span class="inline-flex items-center gap-1.5 rounded-pill bg-line-passive px-2.5 py-1 text-micro text-ink-strong">
          {{ t("admin.tenants.filteredByOwner") }}
          <button type="button" class="text-ink-faint outline-none transition hover:text-ink focus-visible:shadow-focus" :aria-label="t('admin.common.clearFilters')" @click="clearOwnerFilter">
            <Icon name="X" :size="12" />
          </button>
        </span>
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
        <Card v-for="tn in result.data" :key="tn.id" padding="compact">
          <button type="button" class="block w-full text-left rounded-lg outline-none focus-visible:shadow-focus" @click="open(tn)">
            <div class="flex items-center gap-2">
              <Pill :tone="tenantTone(tn.status)">{{ t(`admin.status.tenant.${tn.status}`) }}</Pill>
              <span class="text-micro text-ink-faint">{{ tn.ownerName ?? "—" }}</span>
            </div>
            <p class="mt-1 text-body font-medium text-ink">{{ tn.name }}</p>
            <p class="text-caption text-ink-muted">{{ tn.email }}</p>
            <p class="mt-1 text-micro text-ink-faint">{{ tn.propertyName ?? "—" }} · {{ tn.unitLabel ?? "—" }}</p>
          </button>
        </Card>
      </template>
    </DataTableShell>
  </div>
</template>
