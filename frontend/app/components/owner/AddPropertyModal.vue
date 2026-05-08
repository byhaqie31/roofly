<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { propertyInputSchema } from "~/schemas/property";
import { MY_STATES } from "~/types/property";
import type { Property, PropertyInput } from "~/types/property";
import { useToast } from "~/composables/useToast";
import Modal from "~/components/ui/Modal.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{
  "update:open": [value: boolean];
  created: [property: Property];
}>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);

const initialValues: PropertyInput = {
  name: "",
  address: "",
  city: "",
  state: "",
  postcode: "",
  type: "condo",
};

const { defineField, handleSubmit, errors, resetForm } = useForm<PropertyInput>(
  {
    validationSchema: toTypedSchema(propertyInputSchema),
    initialValues,
  },
);

const [name] = defineField("name");
const [address] = defineField("address");
const [city] = defineField("city");
const [state] = defineField("state");
const [postcode] = defineField("postcode");
const [type] = defineField("type");

const typeOptions = computed(() => [
  { value: "condo", label: t("owner.properties.types.condo") },
  { value: "landed", label: t("owner.properties.types.landed") },
  { value: "shoplot", label: t("owner.properties.types.shoplot") },
  { value: "room", label: t("owner.properties.types.room") },
]);

const stateOptions = MY_STATES.map((s) => ({ value: s, label: s }));

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const created = await useProperties().create(values);
    emit("created", created);
    emit("update:open", false);
    show(t("owner.properties.addModal.createdToast"), "success");
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
    :title="t('owner.properties.addModal.title')"
    :description="t('owner.properties.addModal.description')"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form
      id="add-property-form"
      class="space-y-4"
      @submit.prevent="onSubmit"
    >
      <Input
        v-model="name"
        :label="t('owner.properties.addModal.fields.name')"
        :placeholder="t('owner.properties.addModal.placeholders.name')"
        :error="errors.name"
      />

      <Select
        v-model="type"
        :label="t('owner.properties.addModal.fields.type')"
        :options="typeOptions"
        :error="errors.type"
      />

      <Input
        v-model="address"
        :label="t('owner.properties.addModal.fields.address')"
        :placeholder="t('owner.properties.addModal.placeholders.address')"
        :error="errors.address"
      />

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input
          v-model="city"
          :label="t('owner.properties.addModal.fields.city')"
          :error="errors.city"
        />
        <Select
          v-model="state"
          :label="t('owner.properties.addModal.fields.state')"
          :placeholder="t('owner.properties.addModal.placeholders.state')"
          :options="stateOptions"
          :error="errors.state"
        />
      </div>

      <Input
        v-model="postcode"
        :label="t('owner.properties.addModal.fields.postcode')"
        :placeholder="t('owner.properties.addModal.placeholders.postcode')"
        :error="errors.postcode"
      />
    </form>

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
        form="add-property-form"
        variant="primary"
        :loading="submitting"
      >
        {{ t("owner.properties.addModal.submit") }}
      </Button>
    </template>
  </Modal>
</template>
