<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { recordPaymentFormSchema } from "~/schemas/payment";
import type { InvoiceWithRefs } from "~/services/useInvoices";
import { useToast } from "~/composables/useToast";
import Modal from "~/components/ui/Modal.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{
  open: boolean;
  row: InvoiceWithRefs | null;
}>();

const emit = defineEmits<{
  "update:open": [value: boolean];
  recorded: [invoiceId: string];
}>();

const { t } = useI18n();
const { show } = useToast();
const { formatRM } = useMoney();
const submitting = ref(false);

const senToRinggit = (sen: number) => sen / 100;
const ringgitToSen = (rm: number) => Math.round(rm * 100);

const buildInitialValues = () => {
  const inv = props.row?.invoice;
  const totalDue = (inv?.amount ?? 0) + (inv?.lateFee ?? 0);
  return {
    amount: totalDue ? senToRinggit(totalDue) : 0,
    method: "fpx" as const,
    paidAt: new Date().toISOString().slice(0, 10),
    reference: "",
  };
};

const { defineField, handleSubmit, errors, resetForm } = useForm({
  validationSchema: toTypedSchema(recordPaymentFormSchema),
  initialValues: buildInitialValues(),
});

const [amount] = defineField("amount");
const [method] = defineField("method");
const [paidAt] = defineField("paidAt");
const [reference] = defineField("reference");

const methodOptions = computed(() => [
  { value: "fpx", label: t("owner.payments.methods.fpx") },
  { value: "card", label: t("owner.payments.methods.card") },
  { value: "cash", label: t("owner.payments.methods.cash") },
  { value: "transfer", label: t("owner.payments.methods.transfer") },
]);

const onSubmit = handleSubmit(async (values) => {
  if (!props.row) return;
  submitting.value = true;
  try {
    await useInvoices().recordPayment({
      invoiceId: props.row.invoice.id,
      amount: ringgitToSen(values.amount),
      method: values.method,
      paidAt: new Date(`${values.paidAt}T00:00:00`).toISOString(),
      reference: values.reference || undefined,
    });
    show(t("owner.payments.recordedToast"), "success");
    emit("recorded", props.row.invoice.id);
    emit("update:open", false);
  } finally {
    submitting.value = false;
  }
});

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) resetForm({ values: buildInitialValues() });
  },
);
</script>

<template>
  <Modal
    :open="open"
    :title="t('owner.payments.recordModal.title')"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <div v-if="row" class="space-y-4">
      <div class="rounded-md border border-line-passive bg-surface-page p-4">
        <div class="text-caption text-ink-muted">
          {{ row.invoice.invoiceNumber }} ·
          {{ t("owner.payments.dueOn", { date: row.invoice.dueDate }) }}
        </div>
        <div class="mt-1 text-card-title font-semibold text-ink tabular-nums">
          {{ formatRM(row.invoice.amount + row.invoice.lateFee) }}
        </div>
        <div class="mt-1 text-caption text-ink-muted">
          {{ row.tenant?.name }} · {{ row.unit?.label }}
        </div>
      </div>

      <form
        id="record-payment-form"
        class="space-y-4"
        @submit.prevent="onSubmit"
      >
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Input
            v-model="amount"
            type="number"
            step="0.01"
            :min="0"
            :label="t('owner.payments.fields.amount')"
            :error="errors.amount"
          />
          <Input
            v-model="paidAt"
            type="date"
            :label="t('owner.payments.fields.paidAt')"
            :error="errors.paidAt"
          />
        </div>

        <Select
          v-model="method"
          :options="methodOptions"
          :label="t('owner.payments.fields.method')"
          :error="errors.method"
        />

        <Input
          v-model="reference"
          :label="t('owner.payments.fields.reference')"
          :placeholder="t('owner.payments.placeholders.reference')"
          :error="errors.reference"
        />
      </form>
    </div>

    <template #footer>
      <Button
        variant="ghost"
        :disabled="submitting"
        @click="emit('update:open', false)"
      >
        {{ t("common.cancel") }}
      </Button>
      <Button
        type="submit"
        form="record-payment-form"
        variant="primary"
        :loading="submitting"
        :disabled="!row"
      >
        {{ t("owner.payments.recordCta") }}
      </Button>
    </template>
  </Modal>
</template>
