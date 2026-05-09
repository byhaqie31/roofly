<script setup lang="ts">
import { computed } from "vue";
import type { InvoiceStatus } from "~/types/invoice";
import type { InvoiceWithRefs } from "~/services/useInvoices";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{ row: InvoiceWithRefs }>();

const emit = defineEmits<{
  view: [row: InvoiceWithRefs];
  record: [row: InvoiceWithRefs];
}>();

const { t } = useI18n();
const { formatRM } = useMoney();

const statusToneMap = {
  pending: "pending",
  paid: "paid",
  overdue: "overdue",
  cancelled: "cancelled",
} as const satisfies Record<InvoiceStatus, string>;

const total = computed(
  () => props.row.invoice.amount + props.row.invoice.lateFee,
);

const isUnpaid = computed(
  () =>
    props.row.invoice.status === "pending" ||
    props.row.invoice.status === "overdue",
);

const amountToneClass = computed(() => {
  switch (props.row.invoice.status) {
    case "overdue":
      return "text-status-overdue";
    case "paid":
      return "text-status-paid";
    case "cancelled":
      return "text-ink-muted line-through";
    default:
      return "text-ink";
  }
});

const formatDate = (iso: string) => {
  if (!iso) return "—";
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
};

const paidLabel = computed(() => {
  const p = props.row.payments[0];
  return p
    ? t("owner.payments.paidOn", { date: formatDate(p.paidAt.slice(0, 10)) })
    : "—";
});

const onCardClick = () => emit("view", props.row);
const onCardKeydown = (e: KeyboardEvent) => {
  if (e.key === "Enter" || e.key === " ") {
    e.preventDefault();
    emit("view", props.row);
  }
};
const onRecord = (e: Event) => {
  e.stopPropagation();
  emit("record", props.row);
};
</script>

<template>
  <div
    role="button"
    tabindex="0"
    class="block w-full cursor-pointer rounded-lg border border-line-passive bg-surface-raised p-4 text-left outline-none transition hover:border-line-interactive focus-visible:shadow-focus"
    @click="onCardClick"
    @keydown="onCardKeydown"
  >
    <div class="flex items-start justify-between gap-3">
      <Pill :tone="statusToneMap[row.invoice.status]">
        {{ t(`owner.payments.status.${row.invoice.status}`) }}
      </Pill>
      <div class="text-right">
        <div
          :class="[
            'text-card-title font-semibold tabular-nums',
            amountToneClass,
          ]"
        >
          {{ formatRM(total) }}
        </div>
        <div
          v-if="row.invoice.lateFee > 0"
          class="text-micro tabular-nums text-status-overdue"
        >
          + {{ formatRM(row.invoice.lateFee) }} {{ t("owner.payments.late") }}
        </div>
      </div>
    </div>

    <div class="mt-3 min-w-0">
      <div class="truncate text-body font-medium text-ink">
        {{ row.tenant?.name ?? t("owner.agreements.unknownTenant") }}
      </div>
      <div class="truncate text-caption tabular-nums text-ink-muted">
        {{ row.invoice.invoiceNumber }}
      </div>
    </div>

    <div
      class="mt-3 flex flex-col gap-1 text-caption text-ink-muted"
    >
      <span class="inline-flex items-center gap-1.5 truncate">
        <Icon name="Building2" :size="14" class="shrink-0" />
        <span class="truncate">
          {{ row.property?.name ?? "—" }} · {{ row.unit?.label ?? "—" }}
        </span>
      </span>
      <span class="inline-flex items-center gap-1.5 tabular-nums">
        <Icon name="Calendar" :size="14" class="shrink-0" />
        {{ t("owner.payments.dueOn", { date: formatDate(row.invoice.dueDate) }) }}
      </span>
    </div>

    <div
      class="mt-4 flex items-center justify-between gap-2 border-t border-line-passive pt-3"
    >
      <span
        v-if="!isUnpaid"
        class="text-caption text-ink-faint"
      >
        {{ paidLabel }}
      </span>
      <span v-else />
      <Button
        v-if="isUnpaid"
        variant="primary"
        size="sm"
        @click="onRecord"
      >
        {{ t("owner.payments.recordCta") }}
      </Button>
    </div>
  </div>
</template>
