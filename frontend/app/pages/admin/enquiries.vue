<script setup lang="ts">
import { computed, h, onMounted, ref, watch } from "vue";
import { FlexRender, getCoreRowModel, useVueTable, type ColumnDef } from "@tanstack/vue-table";
import Card from "~/components/ui/Card.vue";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";
import Pill from "~/components/ui/Pill.vue";
import DataTableShell from "~/components/admin/DataTableShell.vue";
import LeadDrawer from "~/components/admin/LeadDrawer.vue";
import NoAccess from "~/components/admin/NoAccess.vue";
import { downloadCsvText } from "~/utils/csv";
import { formatAdminDate } from "~/utils/adminDate";
import type { AdminLead, LeadListQuery } from "~/types/analytics";
import type { Paginated } from "~/types/admin";

// The coming-soon inbox: every email left via the "Notify me" form (POST /waitlist).
// Same leads endpoints as Analytics, with `source` pinned to "waitlist" so this
// page never mixes in registrations — it is the list to follow up, not the funnel.
definePageMeta({ layout: "admin" });
const { can } = useAdminPermissions();
const { t } = useI18n();
useHead({ title: () => t("admin.nav.enquiries") });

const route = useRoute();
const router = useRouter();

const q = ref(String(route.query.q ?? ""));
const page = ref(Number(route.query.page ?? 1));

const loading = ref(true);
const result = ref<Paginated<AdminLead>>({ data: [], meta: { page: 1, perPage: 20, total: 0, lastPage: 1 } });

const query = computed<LeadListQuery>(() => ({
  q: q.value || undefined,
  source: "waitlist",
  page: page.value,
}));

const load = async () => {
  loading.value = true;
  try {
    result.value = await useAdminAnalytics().leads(query.value);
  } finally {
    loading.value = false;
  }
  syncRoute();
};

function syncRoute() {
  router.replace({
    query: Object.fromEntries(
      Object.entries({
        q: q.value || undefined,
        page: page.value > 1 ? String(page.value) : undefined,
      }).filter(([, v]) => v !== undefined),
    ) as Record<string, string>,
  });
}

onMounted(() => {
  if (can("analytics.view")) load();
});

watch(page, load);

let debounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (debounce) clearTimeout(debounce);
  debounce = setTimeout(() => {
    if (page.value !== 1) page.value = 1; // watch(page) will load
    else load();
  }, 300);
});

const fmtDate = formatAdminDate;

// ── Drawer ─────────────────────────────────────────────────────────────
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

const statusCell = (lead: AdminLead) => {
  const ownerId = lead.convertedUserId;
  if (!ownerId) return h(Pill, { tone: "neutral" }, { default: () => t("admin.enquiries.status.waiting") });
  return h("span", { class: "inline-flex items-center gap-2" }, [
    h(Pill, { tone: "active" }, { default: () => t("admin.enquiries.status.registered") }),
    h(
      "a",
      {
        href: `/admin/owners/${ownerId}`,
        class: "text-caption text-ink underline underline-offset-2",
        onClick: (e: MouseEvent) => goToOwner(ownerId, e),
        onKeydown: (e: KeyboardEvent) => e.stopPropagation(),
      },
      lead.convertedOwnerName ?? "",
    ),
  ]);
};

const columns = computed<ColumnDef<AdminLead>[]>(() => [
  { id: "email", header: () => t("admin.enquiries.columns.email"), cell: (i) => h("span", { class: "text-body text-ink" }, i.row.original.email) },
  { id: "signedUp", header: () => t("admin.enquiries.columns.signedUp"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.firstSeenAt)) },
  { id: "lastSeen", header: () => t("admin.enquiries.columns.lastSeen"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.lastSeenAt)) },
  { id: "status", header: () => t("admin.enquiries.columns.status"), cell: (i) => statusCell(i.row.original) },
]);

const table = useVueTable({
  get data() { return result.value.data; },
  get columns() { return columns.value; },
  getCoreRowModel: getCoreRowModel(),
  manualPagination: true,
});

const exporting = ref(false);
const exportCsv = async () => {
  exporting.value = true;
  try {
    const csv = await useAdminAnalytics().exportCsv({ ...query.value, page: undefined });
    downloadCsvText(`roofly-enquiries-${new Date().toISOString().slice(0, 10)}.csv`, csv);
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
    <header class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.enquiries.title") }}</h1>
        <p class="mt-2 text-caption text-ink-muted">{{ t("admin.enquiries.subtitle") }}</p>
      </div>
      <Button variant="ghost" size="sm" class="self-start" :loading="exporting" @click="exportCsv">{{ t("admin.common.exportCsv") }}</Button>
    </header>

    <Card padding="compact" class="mb-4 sm:mb-6">
      <Input v-model="q" :placeholder="t('admin.enquiries.searchPlaceholder')" class="sm:max-w-md" />
    </Card>

    <DataTableShell
      :loading="loading"
      :empty="result.data.length === 0"
      :empty-title="t('admin.enquiries.empty')"
      :empty-description="t('admin.enquiries.emptyHelp')"
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
        <Card v-for="lead in result.data" :key="lead.id" padding="compact">
          <button type="button" class="block w-full text-left rounded-lg outline-none focus-visible:shadow-focus" @click="openLead(lead)">
            <div class="flex items-center gap-2">
              <Pill :tone="lead.convertedUserId ? 'active' : 'neutral'">
                {{ lead.convertedUserId ? t("admin.enquiries.status.registered") : t("admin.enquiries.status.waiting") }}
              </Pill>
              <span class="text-micro text-ink-faint">{{ t("admin.enquiries.columns.signedUp") }} {{ fmtDate(lead.firstSeenAt) }}</span>
            </div>
            <p class="mt-1 text-body font-medium text-ink">{{ lead.email }}</p>
            <p class="text-caption text-ink-muted">
              {{ t("admin.enquiries.columns.lastSeen") }} {{ fmtDate(lead.lastSeenAt) }}
              <template v-if="lead.convertedOwnerName"> · {{ lead.convertedOwnerName }}</template>
            </p>
          </button>
        </Card>
      </template>
    </DataTableShell>

    <LeadDrawer v-model:open="drawerOpen" :lead-id="selectedLeadId" />
  </div>
</template>
