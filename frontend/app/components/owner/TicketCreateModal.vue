<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { ticketCreateFormSchema } from "~/schemas/ticket";
import type { Ticket, TicketInput } from "~/types/ticket";
import type { Unit } from "~/types/unit";
import type { Property } from "~/types/property";
import type { Tenant } from "~/types/tenant";
import { useToast } from "~/composables/useToast";
import Modal from "~/components/ui/Modal.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{
  open: boolean;
  units: Unit[];
  properties: Property[];
  tenants: Tenant[];
}>();

const emit = defineEmits<{
  "update:open": [value: boolean];
  created: [ticket: Ticket];
}>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);

const initialValues: TicketInput = {
  unitId: "",
  reporterId: "owner-1",
  reporterRole: "owner",
  category: "other",
  priority: "medium",
  title: "",
  description: "",
};

const { defineField, handleSubmit, errors, resetForm, setFieldValue } =
  useForm<TicketInput>({
    validationSchema: toTypedSchema(ticketCreateFormSchema),
    initialValues,
  });

const [unitId] = defineField("unitId");
const [reporterId] = defineField("reporterId");
const [category] = defineField("category");
const [priority] = defineField("priority");
const [title] = defineField("title");
const [description] = defineField("description");

const unitOptions = computed(() =>
  props.units.map((u) => {
    const property = props.properties.find((p) => p.id === u.propertyId);
    return {
      value: u.id,
      label: property ? `${property.name} · ${u.label}` : u.label,
    };
  }),
);

// Reporter options: "Me (owner)" plus any tenant. Keep it flat — no
// per-unit auto-derivation; demo flexibility wins over realism.
const reporterOptions = computed(() => [
  { value: "owner-1", label: t("owner.tickets.create.reporterOwner") },
  ...props.tenants.map((tn) => ({
    value: tn.id,
    label: tn.name,
  })),
]);

// Whenever the reporter changes, set role accordingly.
watch(reporterId, (next) => {
  setFieldValue("reporterRole", next === "owner-1" ? "owner" : "tenant");
});

const categoryOptions = computed(() => [
  { value: "plumbing", label: t("owner.tickets.category.plumbing") },
  { value: "electrical", label: t("owner.tickets.category.electrical") },
  { value: "appliance", label: t("owner.tickets.category.appliance") },
  { value: "structural", label: t("owner.tickets.category.structural") },
  { value: "pest", label: t("owner.tickets.category.pest") },
  { value: "other", label: t("owner.tickets.category.other") },
]);

const priorityOptions = computed(() => [
  { value: "low", label: t("owner.tickets.priority.low") },
  { value: "medium", label: t("owner.tickets.priority.medium") },
  { value: "high", label: t("owner.tickets.priority.high") },
  { value: "urgent", label: t("owner.tickets.priority.urgent") },
]);

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const created = await useTickets().create(values);
    emit("created", created);
    emit("update:open", false);
    show(t("owner.tickets.create.createdToast"), "success");
  } finally {
    submitting.value = false;
  }
});

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) resetForm({ values: initialValues });
  },
);
</script>

<template>
  <Modal
    :open="open"
    :title="t('owner.tickets.create.title')"
    :description="t('owner.tickets.create.description')"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form class="space-y-4" @submit.prevent="onSubmit">
      <Select
        v-model="unitId"
        :options="unitOptions"
        :label="t('owner.tickets.create.fields.unit')"
        :placeholder="t('owner.tickets.create.placeholders.unit')"
        :error="errors.unitId"
      />

      <Select
        v-model="reporterId"
        :options="reporterOptions"
        :label="t('owner.tickets.create.fields.reporter')"
        :error="errors.reporterId"
      />

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Select
          v-model="category"
          :options="categoryOptions"
          :label="t('owner.tickets.create.fields.category')"
          :error="errors.category"
        />
        <Select
          v-model="priority"
          :options="priorityOptions"
          :label="t('owner.tickets.create.fields.priority')"
          :error="errors.priority"
        />
      </div>

      <Input
        v-model="title"
        :label="t('owner.tickets.create.fields.title')"
        :placeholder="t('owner.tickets.create.placeholders.title')"
        :error="errors.title"
      />

      <div>
        <label
          class="mb-1.5 block text-caption font-normal text-ink-strong"
        >
          {{ t("owner.tickets.create.fields.description") }}
        </label>
        <textarea
          v-model="description"
          rows="4"
          :placeholder="t('owner.tickets.create.placeholders.description')"
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

      <footer class="flex justify-end gap-2 pt-2">
        <Button
          type="button"
          variant="ghost"
          :disabled="submitting"
          @click="emit('update:open', false)"
        >
          {{ t("common.cancel") }}
        </Button>
        <Button type="submit" variant="primary" :loading="submitting">
          {{ t("owner.tickets.create.submit") }}
        </Button>
      </footer>
    </form>
  </Modal>
</template>
