<script setup lang="ts">
import { computed, ref } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { propertyDetailsFormSchema } from "~/schemas/property";
import { MY_STATES } from "~/types/property";
import type { Property } from "~/types/property";
import { useToast } from "~/composables/useToast";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{ property: Property }>();
const emit = defineEmits<{ saved: [property: Property] }>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);

const initialValues = {
  name: props.property.name,
  internalLabel: props.property.internalLabel ?? "",
  type: props.property.type,
  notes: props.property.notes ?? "",
  address: props.property.address,
  city: props.property.city,
  state: props.property.state,
  postcode: props.property.postcode,
  yearBuilt: props.property.yearBuilt,
  builtUpSqft: props.property.builtUpSqft,
  landSqft: props.property.landSqft,
  bedrooms: props.property.bedrooms,
  bathrooms: props.property.bathrooms,
  parkingLots: props.property.parkingLots,
  furnishing: props.property.furnishing,
};

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: toTypedSchema(propertyDetailsFormSchema),
  initialValues,
});

const [name] = defineField("name");
const [internalLabel] = defineField("internalLabel");
const [type] = defineField("type");
const [notes] = defineField("notes");
const [address] = defineField("address");
const [city] = defineField("city");
const [state] = defineField("state");
const [postcode] = defineField("postcode");
const [yearBuilt] = defineField("yearBuilt");
const [builtUpSqft] = defineField("builtUpSqft");
const [landSqft] = defineField("landSqft");
const [bedrooms] = defineField("bedrooms");
const [bathrooms] = defineField("bathrooms");
const [parkingLots] = defineField("parkingLots");
const [furnishing] = defineField("furnishing");

const typeOptions = computed(() => [
  { value: "condo", label: t("owner.properties.types.condo") },
  { value: "landed", label: t("owner.properties.types.landed") },
  { value: "shoplot", label: t("owner.properties.types.shoplot") },
  { value: "room", label: t("owner.properties.types.room") },
]);

const stateOptions = MY_STATES.map((s) => ({ value: s, label: s }));

const furnishingOptions = computed(() => [
  {
    value: "unfurnished",
    label: t("owner.properties.detail.furnishingOptions.unfurnished"),
  },
  {
    value: "partial",
    label: t("owner.properties.detail.furnishingOptions.partial"),
  },
  {
    value: "fully",
    label: t("owner.properties.detail.furnishingOptions.fully"),
  },
]);

const showLandSqft = computed(() => type.value === "landed" || type.value === "shoplot");

const currentYear = new Date().getFullYear();

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const updated = await useProperties().update(props.property.id, {
      name: values.name,
      internalLabel: values.internalLabel || undefined,
      type: values.type,
      notes: values.notes || undefined,
      address: values.address,
      city: values.city,
      state: values.state,
      postcode: values.postcode,
      yearBuilt: values.yearBuilt,
      builtUpSqft: values.builtUpSqft,
      landSqft: values.landSqft,
      bedrooms: values.bedrooms,
      bathrooms: values.bathrooms,
      parkingLots: values.parkingLots,
      furnishing: values.furnishing,
    });
    show(t("common.savedToast"), "success");
    emit("saved", updated);
  } finally {
    submitting.value = false;
  }
});

const sectionHeading =
  "text-caption font-semibold uppercase tracking-wide text-ink-muted";
</script>

<template>
  <form class="space-y-6" @submit.prevent="onSubmit">
    <section class="space-y-4">
      <h3 :class="sectionHeading">
        {{ t("owner.properties.detail.sections.identity") }}
      </h3>
      <Input
        v-model="name"
        :label="t('owner.properties.addModal.fields.name')"
        :placeholder="t('owner.properties.addModal.placeholders.name')"
        :error="errors.name"
      />
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input
          v-model="internalLabel"
          :label="t('owner.properties.detail.fields.internalLabel')"
          :placeholder="t('owner.properties.detail.placeholders.internalLabel')"
          :error="errors.internalLabel"
        />
        <Select
          v-model="type"
          :options="typeOptions"
          :label="t('owner.properties.addModal.fields.type')"
          :error="errors.type"
        />
      </div>
      <label class="block">
        <span class="mb-1.5 block text-caption font-normal text-ink-strong">
          {{ t("owner.properties.detail.fields.notes") }}
        </span>
        <textarea
          v-model="notes"
          rows="3"
          class="w-full rounded-sm border border-line-passive bg-surface-page px-3 py-2 text-body outline-none transition focus:border-line-interactive focus:shadow-focus"
          :placeholder="t('owner.properties.detail.placeholders.notes')"
        />
      </label>
    </section>

    <section class="space-y-4">
      <h3 :class="sectionHeading">
        {{ t("owner.properties.detail.sections.location") }}
      </h3>
      <Input
        v-model="address"
        :label="t('owner.properties.addModal.fields.address')"
        :error="errors.address"
      />
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Input
          v-model="city"
          :label="t('owner.properties.addModal.fields.city')"
          :error="errors.city"
        />
        <Select
          v-model="state"
          :options="stateOptions"
          :label="t('owner.properties.addModal.fields.state')"
          :placeholder="t('owner.properties.addModal.placeholders.state')"
          :error="errors.state"
        />
        <Input
          v-model="postcode"
          :label="t('owner.properties.addModal.fields.postcode')"
          :error="errors.postcode"
        />
      </div>
    </section>

    <section class="space-y-4">
      <h3 :class="sectionHeading">
        {{ t("owner.properties.detail.sections.specifications") }}
      </h3>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Input
          v-model="yearBuilt"
          type="number"
          :min="1900"
          :max="currentYear"
          :label="t('owner.properties.detail.fields.yearBuilt')"
          :error="errors.yearBuilt"
        />
        <Input
          v-model="builtUpSqft"
          type="number"
          :min="0"
          :label="t('owner.properties.detail.fields.builtUpSqft')"
          :error="errors.builtUpSqft"
        />
        <Input
          v-if="showLandSqft"
          v-model="landSqft"
          type="number"
          :min="0"
          :label="t('owner.properties.detail.fields.landSqft')"
          :error="errors.landSqft"
        />
        <Input
          v-model="bedrooms"
          type="number"
          :min="0"
          :max="20"
          :label="t('owner.properties.detail.fields.bedrooms')"
          :error="errors.bedrooms"
        />
        <Input
          v-model="bathrooms"
          type="number"
          :min="0"
          :max="20"
          :label="t('owner.properties.detail.fields.bathrooms')"
          :error="errors.bathrooms"
        />
        <Input
          v-model="parkingLots"
          type="number"
          :min="0"
          :max="20"
          :label="t('owner.properties.detail.fields.parkingLots')"
          :error="errors.parkingLots"
        />
      </div>
      <Select
        v-model="furnishing"
        :options="furnishingOptions"
        :label="t('owner.properties.detail.fields.furnishing')"
        :error="errors.furnishing"
      />
    </section>

    <div class="flex justify-end pt-2">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.properties.detail.save") }}
      </Button>
    </div>
  </form>
</template>
