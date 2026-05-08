<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { unitInputSchema } from "~/schemas/unit";
import type { Unit, UnitInput } from "~/types/unit";
import { useToast } from "~/composables/useToast";
import Modal from "~/components/ui/Modal.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";

const props = withDefaults(
  defineProps<{
    open: boolean;
    propertyId: string;
    unit?: Unit | null;
  }>(),
  { unit: null },
);

const emit = defineEmits<{
  "update:open": [value: boolean];
  saved: [unit: Unit];
  deleted: [unitId: string];
}>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);
const deleting = ref(false);
const confirmingDelete = ref(false);

const isEditMode = computed(() => !!props.unit);

const buildInitialValues = (): UnitInput => ({
  propertyId: props.propertyId,
  label: props.unit?.label ?? "",
  bedrooms: props.unit?.bedrooms,
  bathrooms: props.unit?.bathrooms,
  sqft: props.unit?.sqft,
  status: props.unit?.status ?? "vacant",
});

const { defineField, handleSubmit, errors, resetForm } = useForm<UnitInput>({
  validationSchema: toTypedSchema(unitInputSchema),
  initialValues: buildInitialValues(),
});

const [label] = defineField("label");
const [bedrooms] = defineField("bedrooms");
const [bathrooms] = defineField("bathrooms");
const [sqft] = defineField("sqft");
const [status] = defineField("status");

const statusOptions = computed(() => [
  { value: "vacant", label: t("owner.units.status.vacant") },
  { value: "occupied", label: t("owner.units.status.occupied") },
  { value: "maintenance", label: t("owner.units.status.maintenance") },
]);

const modalTitle = computed(() => {
  if (confirmingDelete.value) return t("owner.units.deleteConfirm.title");
  return isEditMode.value
    ? t("owner.units.editTitle")
    : t("owner.units.addTitle");
});

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    if (isEditMode.value && props.unit) {
      const updated = await useUnits().update(props.unit.id, {
        label: values.label,
        bedrooms: values.bedrooms,
        bathrooms: values.bathrooms,
        sqft: values.sqft,
        status: values.status,
      });
      show(t("common.savedToast"), "success");
      emit("saved", updated);
    } else {
      const created = await useUnits().create(values);
      show(t("owner.units.createdToast"), "success");
      emit("saved", created);
    }
    emit("update:open", false);
  } finally {
    submitting.value = false;
  }
});

const onConfirmDelete = async () => {
  if (!props.unit) return;
  deleting.value = true;
  try {
    await useUnits().remove(props.unit.id);
    show(t("owner.units.deletedToast"), "success");
    emit("deleted", props.unit.id);
    emit("update:open", false);
  } finally {
    deleting.value = false;
  }
};

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetForm({ values: buildInitialValues() });
      confirmingDelete.value = false;
    }
  },
);
</script>

<template>
  <Modal
    :open="open"
    :title="modalTitle"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <div v-if="confirmingDelete">
      <p class="text-body text-ink">
        {{
          t("owner.units.deleteConfirm.body", {
            label: props.unit?.label ?? "",
          })
        }}
      </p>
    </div>

    <form
      v-else
      id="unit-form"
      class="space-y-4"
      @submit.prevent="onSubmit"
    >
      <Input
        v-model="label"
        :label="t('owner.units.fields.label')"
        :placeholder="t('owner.units.placeholders.label')"
        :error="errors.label"
      />

      <Select
        v-model="status"
        :options="statusOptions"
        :label="t('owner.units.fields.status')"
        :error="errors.status"
      />

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Input
          v-model="bedrooms"
          type="number"
          :min="0"
          :max="20"
          :label="t('owner.units.fields.bedrooms')"
          :error="errors.bedrooms"
        />
        <Input
          v-model="bathrooms"
          type="number"
          :min="0"
          :max="20"
          :label="t('owner.units.fields.bathrooms')"
          :error="errors.bathrooms"
        />
        <Input
          v-model="sqft"
          type="number"
          :min="0"
          :label="t('owner.units.fields.sqft')"
          :error="errors.sqft"
        />
      </div>
    </form>

    <template #footer>
      <template v-if="confirmingDelete">
        <Button
          variant="ghost"
          :disabled="deleting"
          @click="confirmingDelete = false"
        >
          {{ t("common.cancel") }}
        </Button>
        <Button
          variant="accent"
          :loading="deleting"
          @click="onConfirmDelete"
        >
          {{ t("owner.units.deleteConfirm.confirm") }}
        </Button>
      </template>
      <template v-else>
        <Button
          v-if="isEditMode"
          variant="ghost"
          :disabled="submitting"
          @click="confirmingDelete = true"
        >
          {{ t("owner.units.delete") }}
        </Button>
        <div v-if="isEditMode" class="flex-1" />
        <Button
          variant="ghost"
          :disabled="submitting"
          @click="emit('update:open', false)"
        >
          {{ t("common.cancel") }}
        </Button>
        <Button
          type="submit"
          form="unit-form"
          variant="primary"
          :loading="submitting"
        >
          {{ isEditMode ? t("owner.units.save") : t("owner.units.add") }}
        </Button>
      </template>
    </template>
  </Modal>
</template>
