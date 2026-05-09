<script setup lang="ts">
import { computed, h, onMounted, ref, watch } from "vue";
import {
  FlexRender,
  getCoreRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
  type ColumnDef,
  type SortingState,
} from "@tanstack/vue-table";
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import Select from "~/components/ui/Select.vue";
import RecordPaymentModal from "~/components/owner/RecordPaymentModal.vue";
import InvoiceViewModal from "~/components/owner/InvoiceViewModal.vue";
import InvoicePeriodFilter from "~/components/owner/InvoicePeriodFilter.vue";
import InvoiceCard from "~/components/owner/InvoiceCard.vue";
import type { InvoiceStatus } from "~/types/invoice";
import type { InvoiceWithRefs } from "~/services/useInvoices";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
const { formatRM } = useMoney();
const { isMobile } = useBreakpoint();
useHead({ title: () => t("owner.nav.payments") });

const DESKTOP_PAGE_SIZE = 20;
const MOBILE_PAGE_SIZE = 8;

const rows = ref<InvoiceWithRefs[]>([]);
const loading = ref(true);
const statusFilter = ref<InvoiceStatus | "all">("all");
const filterMonth = ref<string>("all");
const filterYear = ref<string>("all");
const sorting = ref<SortingState>([{ id: "dueDate", desc: true }]);
const showRecordModal = ref(false);
const showViewModal = ref(false);
const selectedRow = ref<InvoiceWithRefs | null>(null);
const viewingRow = ref<InvoiceWithRefs | null>(null);

const refresh = async () => {
  rows.value = await useInvoices().listWithRefs();
};

onMounted(async () => {
  try {
    await refresh();
  } finally {
    loading.value = false;
  }
});

const statusToneMap: Record<InvoiceStatus, string> = {
  pending: "pending",
  paid: "paid",
  overdue: "overdue",
  cancelled: "cancelled",
};

const statusFilters: { value: InvoiceStatus | "all"; key: string }[] = [
  { value: "all", key: "all" },
  { value: "pending", key: "pending" },
  { value: "overdue", key: "overdue" },
  { value: "paid", key: "paid" },
  { value: "cancelled", key: "cancelled" },
];

const dateFilteredRows = computed(() => {
  let result = rows.value;
  if (filterYear.value !== "all") {
    result = result.filter(
      (r) => r.invoice.dueDate.slice(0, 4) === filterYear.value,
    );
  }
  if (filterMonth.value !== "all") {
    result = result.filter(
      (r) => r.invoice.dueDate.slice(5, 7) === filterMonth.value,
    );
  }
  return result;
});

const yearList = computed(() => {
  const years = new Set<string>();
  rows.value.forEach((r) => years.add(r.invoice.dueDate.slice(0, 4)));
  return Array.from(years).sort().reverse();
});

const counts = computed(() => {
  const out: Record<string, number> = { all: dateFilteredRows.value.length };
  dateFilteredRows.value.forEach((r) => {
    out[r.invoice.status] = (out[r.invoice.status] ?? 0) + 1;
  });
  return out;
});

const filteredRows = computed(() =>
  statusFilter.value === "all"
    ? dateFilteredRows.value
    : dateFilteredRows.value.filter(
        (r) => r.invoice.status === statusFilter.value,
      ),
);

const filtersActive = computed(
  () =>
    statusFilter.value !== "all" ||
    filterMonth.value !== "all" ||
    filterYear.value !== "all",
);

const clearFilters = () => {
  statusFilter.value = "all";
  filterMonth.value = "all";
  filterYear.value = "all";
};

const statusSelectOptions = computed(() =>
  statusFilters.map((f) => ({
    value: f.value,
    label: `${t(`owner.payments.filters.${f.key}`)} (${counts.value[f.value] ?? 0})`,
  })),
);

const formatDate = (iso: string) => {
  if (!iso) return "—";
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
};

const onRowAction = (row: InvoiceWithRefs) => {
  selectedRow.value = row;
  showRecordModal.value = true;
};

const onViewInvoice = (row: InvoiceWithRefs) => {
  viewingRow.value = row;
  showViewModal.value = true;
};

const onRecorded = async () => {
  await refresh();
};

const columns = computed<ColumnDef<InvoiceWithRefs>[]>(() => [
  {
    id: "invoiceNumber",
    accessorFn: (row) => row.invoice.invoiceNumber,
    header: () => t("owner.payments.columns.invoice"),
    cell: (info) => {
      const r = info.row.original;
      return h(
        "button",
        {
          type: "button",
          class:
            "text-caption tabular-nums text-ink underline-offset-2 outline-none transition hover:underline focus-visible:shadow-focus",
          onClick: (e: Event) => {
            e.stopPropagation();
            onViewInvoice(r);
          },
        },
        info.getValue() as string,
      );
    },
    enableSorting: false,
  },
  {
    id: "tenant",
    accessorFn: (row) => row.tenant?.name ?? "",
    header: () => t("owner.payments.columns.tenant"),
    cell: (info) => {
      const r = info.row.original;
      return h("div", { class: "min-w-0" }, [
        h(
          "div",
          { class: "truncate text-body text-ink" },
          r.tenant?.name ?? t("owner.agreements.unknownTenant"),
        ),
        h(
          "div",
          { class: "truncate text-caption text-ink-muted" },
          `${r.property?.name ?? "—"} · ${r.unit?.label ?? "—"}`,
        ),
      ]);
    },
    enableSorting: false,
  },
  {
    id: "dueDate",
    accessorFn: (row) => row.invoice.dueDate,
    header: () => t("owner.payments.columns.due"),
    cell: (info) =>
      h(
        "span",
        { class: "text-caption tabular-nums text-ink" },
        formatDate(info.getValue() as string),
      ),
  },
  {
    id: "amount",
    accessorFn: (row) => row.invoice.amount + row.invoice.lateFee,
    header: () => t("owner.payments.columns.amount"),
    cell: (info) => {
      const r = info.row.original;
      const total = r.invoice.amount + r.invoice.lateFee;
      const children = [
        h(
          "div",
          { class: "text-body tabular-nums text-ink" },
          formatRM(total),
        ),
      ];
      if (r.invoice.lateFee > 0) {
        children.push(
          h(
            "div",
            { class: "text-micro tabular-nums text-status-overdue" },
            `+${formatRM(r.invoice.lateFee)} ${t("owner.payments.late")}`,
          ),
        );
      }
      return h("div", { class: "text-right" }, children);
    },
  },
  {
    id: "status",
    accessorFn: (row) => row.invoice.status,
    header: () => t("owner.payments.columns.status"),
    cell: (info) => {
      const status = info.getValue() as InvoiceStatus;
      return h(
        Pill,
        { tone: statusToneMap[status] },
        () => t(`owner.payments.status.${status}`),
      );
    },
    enableSorting: false,
  },
  {
    id: "action",
    header: () => t("owner.payments.columns.action"),
    cell: (info) => {
      const r = info.row.original;
      const isUnpaid =
        r.invoice.status === "pending" || r.invoice.status === "overdue";
      if (!isUnpaid) {
        return h(
          "span",
          { class: "text-micro text-ink-faint" },
          r.payments[0]
            ? t("owner.payments.paidOn", {
                date: formatDate(r.payments[0].paidAt.slice(0, 10)),
              })
            : "—",
        );
      }
      return h(
        Button,
        {
          variant: "ghost",
          size: "sm",
          onClick: (e: Event) => {
            e.stopPropagation();
            onRowAction(r);
          },
        },
        () => t("owner.payments.recordCta"),
      );
    },
    enableSorting: false,
  },
]);

const table = useVueTable({
  get data() {
    return filteredRows.value;
  },
  get columns() {
    return columns.value;
  },
  initialState: {
    pagination: { pageSize: DESKTOP_PAGE_SIZE, pageIndex: 0 },
  },
  state: {
    get sorting() {
      return sorting.value;
    },
  },
  onSortingChange: (updater) => {
    sorting.value =
      typeof updater === "function" ? updater(sorting.value) : updater;
  },
  getCoreRowModel: getCoreRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
});

watch([statusFilter, filterMonth, filterYear], () => {
  table.setPageIndex(0);
});

watch(
  isMobile,
  (mobile) => {
    table.setPageSize(mobile ? MOBILE_PAGE_SIZE : DESKTOP_PAGE_SIZE);
    table.setPageIndex(0);
  },
  { immediate: true },
);

const pageRange = computed(() => {
  const total = filteredRows.value.length;
  if (total === 0) return { from: 0, to: 0, total: 0 };
  const pageIdx = table.getState().pagination.pageIndex;
  const pageSize = table.getState().pagination.pageSize;
  const from = pageIdx * pageSize + 1;
  const to = Math.min(from + pageSize - 1, total);
  return { from, to, total };
});

const pageRows = computed(() => table.getRowModel().rows.map((r) => r.original));

const sortIcon = (id: string) => {
  const sort = sorting.value.find((s) => s.id === id);
  if (!sort) return "ChevronsUpDown";
  return sort.desc ? "ChevronDown" : "ChevronUp";
};
</script>

<template>
  <div>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.nav.payments") }}
      </h1>
    </header>

    <!-- Filters: mobile = status select left + period popover right; desktop = pill row + period popover -->
    <div
      class="mb-4 flex items-center justify-between gap-3"
    >
      <!-- Mobile status: dropdown.
           Keyed on the active count so reka-ui's SelectValue (which captures
           the matching SelectItem's text at mount time, not reactively)
           refreshes when the data fetch resolves or after a record-payment
           refresh changes the count. -->
      <div class="w-44 sm:hidden">
        <Select
          :key="counts[statusFilter] ?? 0"
          v-model="statusFilter"
          :options="statusSelectOptions"
        />
      </div>

      <!-- Desktop status: pill row -->
      <div class="hidden flex-wrap items-center gap-2 sm:flex">
        <button
          v-for="f in statusFilters"
          :key="f.value"
          type="button"
          :class="[
            'inline-flex items-center gap-2 rounded-pill border px-3 py-1.5 text-caption outline-none transition focus-visible:shadow-focus',
            statusFilter === f.value
              ? 'border-line-interactive bg-surface-raised text-ink'
              : 'border-line-passive bg-transparent text-ink-muted hover:text-ink',
          ]"
          @click="statusFilter = f.value"
        >
          {{ t(`owner.payments.filters.${f.key}`) }}
          <span class="tabular-nums text-ink-faint">
            {{ counts[f.value] ?? 0 }}
          </span>
        </button>
        <button
          v-if="filtersActive"
          type="button"
          class="h-9 rounded-sm px-3 text-caption text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus"
          @click="clearFilters"
        >
          {{ t("owner.payments.filters.clear") }}
        </button>
      </div>

      <InvoicePeriodFilter
        v-model:month="filterMonth"
        v-model:year="filterYear"
        :years="yearList"
      />
    </div>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("common.loading") }}
      </p>
    </Card>

    <Card v-else-if="rows.length === 0" padding="loose">
      <EmptyState
        icon="Wallet"
        :title="t('owner.payments.emptyTitle')"
        :description="t('owner.payments.emptyDescription')"
      />
    </Card>

    <template v-else>
      <!-- Mobile: cards -->
      <div class="space-y-3 sm:hidden">
        <InvoiceCard
          v-for="row in pageRows"
          :key="row.invoice.id"
          :row="row"
          @view="onViewInvoice"
          @record="onRowAction"
        />
        <Card
          v-if="pageRows.length === 0"
          padding="loose"
        >
          <p class="text-center text-caption text-ink-muted">
            {{ t("owner.payments.emptyFilter") }}
          </p>
        </Card>
      </div>

      <!-- Desktop: table -->
      <Card class="hidden sm:block" padding="compact">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="border-b border-line-passive">
                <th
                  v-for="header in table.getHeaderGroups()[0].headers"
                  :key="header.id"
                  :class="[
                    'px-3 py-2 text-caption font-semibold text-ink-strong',
                    header.column.id === 'amount' ? 'text-right' : '',
                  ]"
                >
                  <button
                    v-if="header.column.getCanSort()"
                    type="button"
                    class="inline-flex items-center gap-1 outline-none transition hover:text-ink focus-visible:shadow-focus"
                    @click="header.column.toggleSorting()"
                  >
                    <FlexRender
                      :render="header.column.columnDef.header"
                      :props="header.getContext()"
                    />
                    <Icon
                      :name="sortIcon(header.column.id)"
                      :size="14"
                      class="text-ink-muted"
                    />
                  </button>
                  <FlexRender
                    v-else
                    :render="header.column.columnDef.header"
                    :props="header.getContext()"
                  />
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in table.getRowModel().rows"
                :key="row.id"
                class="border-b border-line-passive transition last:border-b-0 hover:bg-[rgba(28,28,28,0.03)]"
              >
                <td
                  v-for="cell in row.getVisibleCells()"
                  :key="cell.id"
                  :class="[
                    'px-3 py-3 align-middle',
                    cell.column.id === 'amount' ? 'text-right' : '',
                  ]"
                >
                  <FlexRender
                    :render="cell.column.columnDef.cell"
                    :props="cell.getContext()"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="filteredRows.length === 0"
          class="py-8 text-center text-caption text-ink-muted"
        >
          {{ t("owner.payments.emptyFilter") }}
        </div>
      </Card>

      <!-- Pagination — shared across mobile + desktop -->
      <div
        v-if="filteredRows.length > 0"
        class="mt-4 flex flex-col gap-3 border-t border-line-passive pt-3 sm:flex-row sm:items-center sm:justify-between"
      >
        <span class="text-caption tabular-nums text-ink-muted">
          {{
            t("owner.payments.pagination.showing", {
              from: pageRange.from,
              to: pageRange.to,
              total: pageRange.total,
            })
          }}
        </span>
        <div class="flex items-center justify-between gap-2 sm:justify-end">
          <Button
            variant="ghost"
            size="sm"
            :disabled="!table.getCanPreviousPage()"
            @click="table.previousPage()"
          >
            <Icon name="ChevronLeft" :size="14" />
            {{ t("owner.payments.pagination.prev") }}
          </Button>
          <span class="text-caption tabular-nums text-ink-muted">
            {{
              t("owner.payments.pagination.page", {
                current: table.getState().pagination.pageIndex + 1,
                total: Math.max(1, table.getPageCount()),
              })
            }}
          </span>
          <Button
            variant="ghost"
            size="sm"
            :disabled="!table.getCanNextPage()"
            @click="table.nextPage()"
          >
            {{ t("owner.payments.pagination.next") }}
            <Icon name="ChevronRight" :size="14" />
          </Button>
        </div>
      </div>
    </template>

    <RecordPaymentModal
      v-model:open="showRecordModal"
      :row="selectedRow"
      @recorded="onRecorded"
    />

    <InvoiceViewModal
      v-model:open="showViewModal"
      :row="viewingRow"
    />
  </div>
</template>
