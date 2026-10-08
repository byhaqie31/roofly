<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { ticketCreateFormSchema } from "~/schemas/ticket";
import type { Ticket, TicketInput } from "~/types/ticket";
import { useToast } from "~/composables/useToast";
import Modal from "~/components/ui/Modal.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{
  open: boolean;
  // The tenant's current unit — issues are filed against it automatically.
  unitId: string | null;
  reporterId: string;
}>();

const emit = defineEmits<{
  "update:open": [value: boolean];
  created: [ticket: Ticket];
}>();

const { t } = useI18n();
const { show } = useToast();
const { toFieldErrors } = useApiError();
const submitting = ref(false);

const makeInitial = (): TicketInput => ({
  unitId: props.unitId ?? "",
  reporterId: props.reporterId,
  reporterRole: "tenant",
  category: "other",
  priority: "medium",
  title: "",
  description: "",
});

const { defineField, handleSubmit, errors, resetForm, setErrors } =
  useForm<TicketInput>({
  validationSchema: toTypedSchema(ticketCreateFormSchema),
  initialValues: makeInitial(),
});

const [category] = defineField("category");
const [priority] = defineField("priority");
const [title] = defineField("title");
const [description] = defineField("description");

const categoryOptions = computed(() => [
  { value: "plumbing", label: t("tenant.tickets.category.plumbing") },
  { value: "electrical", label: t("tenant.tickets.category.electrical") },
  { value: "appliance", label: t("tenant.tickets.category.appliance") },
  { value: "structural", label: t("tenant.tickets.category.structural") },
  { value: "pest", label: t("tenant.tickets.category.pest") },
  { value: "other", label: t("tenant.tickets.category.other") },
]);

const priorityOptions = computed(() => [
  { value: "low", label: t("tenant.tickets.priority.low") },
  { value: "medium", label: t("tenant.tickets.priority.medium") },
  { value: "high", label: t("tenant.tickets.priority.high") },
  { value: "urgent", label: t("tenant.tickets.priority.urgent") },
]);

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const created = await useTickets().createForTenant(values);
    emit("created", created);
    emit("update:open", false);
    show(t("tenant.tickets.report.createdToast"), "success");
  } catch (err) {
    const fieldErrors = toFieldErrors(err);
    if (fieldErrors) {
      setErrors(fieldErrors);
      return;
    }
    show(t("common.genericError"), "danger");
  } finally {
    submitting.value = false;
  }
});

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) resetForm({ values: makeInitial() });
  },
);
</script>

<template>
  <Modal
    :open="open"
    :title="t('tenant.tickets.report.title')"
    :description="t('tenant.tickets.report.description')"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-4" @submit.prevent="onSubmit">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Select
          v-model="category"
          :options="categoryOptions"
          :label="t('tenant.tickets.report.fields.category')"
          :error="errors.category"
        />
        <Select
          v-model="priority"
          :options="priorityOptions"
          :label="t('tenant.tickets.report.fields.priority')"
          :error="errors.priority"
        />
      </div>

      <Input
        v-model="title"
        :label="t('tenant.tickets.report.fields.title')"
        :placeholder="t('tenant.tickets.report.placeholders.title')"
        :error="errors.title"
      />

      <div>
        <label class="mb-1.5 block text-caption font-normal text-ink-strong">
          {{ t("tenant.tickets.report.fields.description") }}
        </label>
        <textarea
          v-model="description"
          rows="4"
          :placeholder="t('tenant.tickets.report.placeholders.description')"
          class="w-full rounded-sm border border-line-passive bg-surface-page px-3 py-2 text-body text-ink outline-none transition focus:border-line-interactive focus:shadow-focus"
        />
        <span
          v-if="errors.description"
          class="mt-1.5 block text-caption text-accent"
          role="alert"
        >
          {{ errors.description }}
        </span>
      </div>
    </form>

    <template #footer>
      <Button
        type="button"
        variant="ghost"
        :disabled="submitting"
        @click="emit('update:open', false)"
      >
        {{ t("common.cancel") }}
      </Button>
      <Button
        type="button"
        variant="primary"
        :loading="submitting"
        @click="onSubmit"
      >
        {{ t("tenant.tickets.report.submit") }}
      </Button>
    </template>
  </Modal>
</template>
