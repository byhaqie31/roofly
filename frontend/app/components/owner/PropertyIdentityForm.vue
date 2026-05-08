<script setup lang="ts">
import { computed, ref } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { propertyInputSchema } from "~/schemas/property";
import { MY_STATES } from "~/types/property";
import type { Property, PropertyInput } from "~/types/property";
import { useToast } from "~/composables/useToast";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{ property: Property }>();
const emit = defineEmits<{ saved: [property: Property] }>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);

const initialValues: PropertyInput = {
  name: props.property.name,
  address: props.property.address,
  city: props.property.city,
  state: props.property.state,
  postcode: props.property.postcode,
  type: props.property.type,
};

const { defineField, handleSubmit, errors } = useForm<PropertyInput>({
  validationSchema: toTypedSchema(propertyInputSchema),
  initialValues,
});

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
    const updated = await useProperties().update(props.property.id, values);
    emit("saved", updated);
    show(t("common.savedToast"), "success");
  } finally {
    submitting.value = false;
  }
});
</script>

<template>
  <form class="space-y-4" @submit.prevent="onSubmit">
    <p class="text-caption text-ink-muted">
      {{ t("owner.properties.detail.identityHelp") }}
    </p>

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

    <div class="flex justify-end pt-2">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.properties.detail.save") }}
      </Button>
    </div>
  </form>
</template>
