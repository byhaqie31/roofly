<script setup lang="ts">
import { computed, ref } from "vue";
import type { InvoiceStatus } from "~/types/invoice";
import type { InvoiceWithRefs } from "~/services/useInvoices";
import { useToast } from "~/composables/useToast";
import Modal from "~/components/ui/Modal.vue";
import Pill from "~/components/ui/Pill.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";

const props = defineProps<{
  open: boolean;
  row: InvoiceWithRefs | null;
}>();

const emit = defineEmits<{
  "update:open": [value: boolean];
}>();

const { t } = useI18n();
const { show } = useToast();
const { formatRM } = useMoney();
const sending = ref(false);

const statusToneMap: Record<InvoiceStatus, string> = {
  pending: "pending",
  paid: "paid",
  overdue: "overdue",
  cancelled: "cancelled",
};

const formatDate = (iso: string) => {
  if (!iso) return "—";
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
};

const total = computed(() => {
  const inv = props.row?.invoice;
  if (!inv) return 0;
  return inv.amount + inv.lateFee;
});

const periodLabel = computed(() => {
  const due = props.row?.invoice.dueDate;
  if (!due) return "";
  const date = new Date(due);
  return date.toLocaleString("en-MY", { month: "long", year: "numeric" });
});

const onDownload = () => {
  show(t("owner.payments.viewModal.downloadToast"), "default");
};

const onSend = async () => {
  if (!props.row) return;
  sending.value = true;
  try {
    await useInvoices().sendInvoice(props.row.invoice.id);
    show(
      t("owner.payments.viewModal.sentToast", {
        email: props.row.tenant?.email ?? "",
      }),
      "success",
    );
  } finally {
    sending.value = false;
  }
};
</script>

<template>
  <Modal
    :open="open"
    :title="row ? row.invoice.invoiceNumber : ''"
    size="lg"
    @update:open="emit('update:open', $event)"
  >
    <div v-if="row" class="space-y-5">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="text-caption text-ink-muted">
            {{ t("owner.payments.viewModal.period") }}
          </div>
          <div class="text-body text-ink">{{ periodLabel }}</div>
          <div class="mt-1 text-caption text-ink-muted">
            {{ t("owner.payments.dueOn", { date: formatDate(row.invoice.dueDate) }) }}
          </div>
        </div>
        <Pill :tone="statusToneMap[row.invoice.status]">
          {{ t(`owner.payments.status.${row.invoice.status}`) }}
        </Pill>
      </div>

      <section>
        <div class="text-caption font-semibold uppercase tracking-wide text-ink-muted">
          {{ t("owner.payments.viewModal.billTo") }}
        </div>
        <div class="mt-2 text-body text-ink">
          {{ row.tenant?.name ?? "—" }}
        </div>
        <div class="text-caption text-ink-muted">
          {{ row.tenant?.email ?? "—" }} · {{ row.tenant?.phone ?? "—" }}
        </div>
      </section>

      <section>
        <div class="text-caption font-semibold uppercase tracking-wide text-ink-muted">
          {{ t("owner.payments.viewModal.property") }}
        </div>
        <div class="mt-2 text-body text-ink">
          {{ row.property?.name ?? "—" }}
        </div>
        <div class="text-caption text-ink-muted">
          {{ row.unit?.label ?? "—" }}
        </div>
      </section>

      <section
        class="rounded-md border border-line-passive bg-surface-page p-4"
      >
        <dl class="space-y-2 text-body">
          <div class="flex items-baseline justify-between">
            <dt class="text-ink-muted">{{ t("owner.payments.viewModal.rent") }}</dt>
            <dd class="tabular-nums text-ink">{{ formatRM(row.invoice.amount) }}</dd>
          </div>
          <div
            v-if="row.invoice.lateFee > 0"
            class="flex items-baseline justify-between"
          >
            <dt class="text-status-overdue">
              {{ t("owner.payments.viewModal.lateFee") }}
            </dt>
            <dd class="tabular-nums text-status-overdue">
              {{ formatRM(row.invoice.lateFee) }}
            </dd>
          </div>
          <div class="flex items-baseline justify-between border-t border-line-passive pt-2">
            <dt class="font-semibold text-ink">
              {{ t("owner.payments.viewModal.total") }}
            </dt>
            <dd class="text-card-title font-semibold tabular-nums text-ink">
              {{ formatRM(total) }}
            </dd>
          </div>
        </dl>
      </section>

      <section v-if="row.payments.length > 0">
        <div class="text-caption font-semibold uppercase tracking-wide text-ink-muted">
          {{ t("owner.payments.viewModal.payments") }}
        </div>
        <ul class="mt-2 divide-y divide-line-passive">
          <li
            v-for="p in row.payments"
            :key="p.id"
            class="flex items-baseline justify-between py-2 text-caption"
          >
            <div>
              <span class="text-ink">
                {{ t(`owner.payments.methods.${p.method}`) }}
              </span>
              <span class="ml-2 text-ink-muted">
                {{ formatDate(p.paidAt.slice(0, 10)) }}
              </span>
              <span v-if="p.reference" class="ml-2 text-ink-faint">
                · {{ p.reference }}
              </span>
            </div>
            <span class="tabular-nums text-ink">{{ formatRM(p.amount) }}</span>
          </li>
        </ul>
      </section>
    </div>

    <template #footer>
      <Button variant="ghost" @click="onDownload">
        <Icon name="Download" :size="14" class="mr-1" />
        {{ t("owner.payments.viewModal.download") }}
      </Button>
      <Button
        v-if="row && row.invoice.status !== 'cancelled'"
        variant="primary"
        :loading="sending"
        @click="onSend"
      >
        <Icon name="Send" :size="14" class="mr-1" />
        {{ t("owner.payments.viewModal.send") }}
      </Button>
    </template>
  </Modal>
</template>
