<script setup lang="ts">
import { computed, h, onMounted, ref, watch } from "vue";
import { FlexRender, getCoreRowModel, useVueTable, type ColumnDef } from "@tanstack/vue-table";
import Card from "~/components/ui/Card.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Pill from "~/components/ui/Pill.vue";
import DataTableShell from "~/components/admin/DataTableShell.vue";
import EnquiryDetailModal from "~/components/admin/EnquiryDetailModal.vue";
import { formatAdminDate } from "~/utils/adminDate";
import { ENQUIRY_STATUSES, ENQUIRY_TYPES, type AdminEnquiry, type AdminEnquiryPage, type EnquiryStatus, type EnquiryType } from "~/types/support";

// Enquiries → Messages tab (support.manage): what owners and tenants sent from the
// in-app help button. Track only — status new → replied → closed, an internal note,
// and a mailto reply; no in-app thread.
const emit = defineEmits<{ "update:newCount": [n: number] }>();
const { t } = useI18n();

const q = ref("");
const status = ref<"all" | EnquiryStatus>("all");
const type = ref<"all" | EnquiryType>("all");
const page = ref(1);
const loading = ref(true);
const result = ref<AdminEnquiryPage>({ data: [], meta: { page: 1, perPage: 20, total: 0, lastPage: 1, newCount: 0 } });

const load = async () => {
  loading.value = true;
  try {
    result.value = await useAdminEnquiries().list({
      q: q.value || undefined,
      status: status.value === "all" ? undefined : status.value,
      type: type.value === "all" ? undefined : type.value,
      page: page.value,
    });
    emit("update:newCount", result.value.meta.newCount);
  } finally {
    loading.value = false;
  }
};

onMounted(load);
watch(page, load);
watch([status, type], () => { if (page.value !== 1) page.value = 1; else load(); });
let debounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (debounce) clearTimeout(debounce);
  debounce = setTimeout(() => { if (page.value !== 1) page.value = 1; else load(); }, 300);
});

type Tone = "pending" | "active" | "neutral" | "high" | "medium" | "low";
const statusTone: Record<EnquiryStatus, Tone> = { new: "pending", replied: "active", closed: "neutral" };
const typeTone: Record<EnquiryType, Tone> = { issue: "high", question: "medium", feedback: "low" };

const statusOptions = computed(() => [
  { value: "all", label: t("admin.enquiries.messages.allStatuses") },
  ...ENQUIRY_STATUSES.map((s) => ({ value: s, label: t(`admin.enquiries.messages.status.${s}`) })),
]);
const typeOptions = computed(() => [
  { value: "all", label: t("admin.enquiries.messages.allTypes") },
  ...ENQUIRY_TYPES.map((k) => ({ value: k, label: t(`admin.enquiries.messages.types.${k}`) })),
]);

const fmtDate = formatAdminDate;

const columns = computed<ColumnDef<AdminEnquiry>[]>(() => [
  { id: "received", header: () => t("admin.enquiries.messages.columns.received"), cell: (i) => h("span", { class: "text-caption tabular-nums whitespace-nowrap" }, fmtDate(i.row.original.createdAt)) },
  {
    id: "from",
    header: () => t("admin.enquiries.messages.columns.from"),
    cell: (i) => h("div", { class: "min-w-0" }, [
      h("p", { class: "text-body text-ink" }, i.row.original.name),
      h("p", { class: "text-micro text-ink-muted" }, [i.row.original.email, i.row.original.role ? ` · ${t(`admin.enquiries.messages.roles.${i.row.original.role}`)}` : ""]),
    ]),
  },
  { id: "type", header: () => t("admin.enquiries.messages.columns.type"), cell: (i) => h(Pill, { tone: typeTone[i.row.original.type] }, { default: () => t(`admin.enquiries.messages.types.${i.row.original.type}`) }) },
  { id: "message", header: () => t("admin.enquiries.messages.columns.message"), cell: (i) => h("p", { class: "text-caption text-ink-muted line-clamp-2 max-w-md" }, i.row.original.message) },
  { id: "status", header: () => t("admin.enquiries.messages.columns.status"), cell: (i) => h(Pill, { tone: statusTone[i.row.original.status] }, { default: () => t(`admin.enquiries.messages.status.${i.row.original.status}`) }) },
]);

const table = useVueTable({
  get data() { return result.value.data; },
  get columns() { return columns.value; },
  getCoreRowModel: getCoreRowModel(),
  manualPagination: true,
});

// ── Detail ─────────────────────────────────────────────────────────────
const detailOpen = ref(false);
const selected = ref<AdminEnquiry | null>(null);
const openDetail = (e: AdminEnquiry) => { selected.value = e; detailOpen.value = true; };
const onSaved = (updated: AdminEnquiry) => {
  // Re-fetch so the row drops out of a status filter it no longer matches, and the badge updates.
  selected.value = updated;
  load();
};
</script>

<template>
  <div>
    <Card padding="compact" class="mb-4 sm:mb-6">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto]">
        <Input v-model="q" :placeholder="t('admin.enquiries.messages.searchPlaceholder')" />
        <Select v-model="status" :options="statusOptions" class="sm:w-44" />
        <Select v-model="type" :options="typeOptions" class="sm:w-44" />
      </div>
    </Card>

    <DataTableShell
      :loading="loading"
      :empty="result.data.length === 0"
      :empty-title="t('admin.enquiries.messages.empty')"
      :empty-description="t('admin.enquiries.messages.emptyHelp')"
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
              @click="openDetail(row.original)"
              @keydown.enter.prevent="openDetail(row.original)"
            >
              <td v-for="cell in row.getVisibleCells()" :key="cell.id" class="px-3 py-3 align-top">
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </td>
            </tr>
          </tbody>
        </table>
      </template>
      <template #cards>
        <Card v-for="e in result.data" :key="e.id" padding="compact">
          <button type="button" class="block w-full text-left rounded-lg outline-none focus-visible:shadow-focus" @click="openDetail(e)">
            <div class="flex flex-wrap items-center gap-2">
              <Pill :tone="statusTone[e.status]">{{ t(`admin.enquiries.messages.status.${e.status}`) }}</Pill>
              <Pill :tone="typeTone[e.type]">{{ t(`admin.enquiries.messages.types.${e.type}`) }}</Pill>
              <span class="text-micro text-ink-faint">{{ fmtDate(e.createdAt) }}</span>
            </div>
            <p class="mt-1 text-body font-medium text-ink">{{ e.name }}</p>
            <p class="text-caption text-ink-muted line-clamp-2">{{ e.message }}</p>
          </button>
        </Card>
      </template>
    </DataTableShell>

    <EnquiryDetailModal v-model:open="detailOpen" :enquiry="selected" @saved="onSaved" />
  </div>
</template>
