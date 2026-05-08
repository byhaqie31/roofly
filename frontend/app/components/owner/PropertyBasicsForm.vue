<script setup lang="ts">
import { computed, ref } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { propertyBasicsSchema } from "~/schemas/property";
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
  yearBuilt: props.property.yearBuilt,
  builtUpSqft: props.property.builtUpSqft,
  landSqft: props.property.landSqft,
  bedrooms: props.property.bedrooms,
  bathrooms: props.property.bathrooms,
  parkingLots: props.property.parkingLots,
  furnishing: props.property.furnishing,
  titleType: props.property.titleType,
  tenureExpiry: props.property.tenureExpiry,
  strataTitle: props.property.strataTitle,
};

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: toTypedSchema(propertyBasicsSchema),
  initialValues,
});

const [yearBuilt] = defineField("yearBuilt");
const [builtUpSqft] = defineField("builtUpSqft");
const [landSqft] = defineField("landSqft");
const [bedrooms] = defineField("bedrooms");
const [bathrooms] = defineField("bathrooms");
const [parkingLots] = defineField("parkingLots");
const [furnishing] = defineField("furnishing");
const [titleType] = defineField("titleType");
const [tenureExpiry] = defineField("tenureExpiry");
const [strataTitle] = defineField("strataTitle");

const showLandSqft = computed(
  () =>
    props.property.type === "landed" || props.property.type === "shoplot",
);

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

const titleTypeOptions = computed(() => [
  {
    value: "freehold",
    label: t("owner.properties.detail.titleTypeOptions.freehold"),
  },
  {
    value: "leasehold",
    label: t("owner.properties.detail.titleTypeOptions.leasehold"),
  },
]);

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

const currentYear = new Date().getFullYear();
</script>

<template>
  <form class="space-y-4" @submit.prevent="onSubmit">
    <p class="text-caption text-ink-muted">
      {{ t("owner.properties.detail.basicsHelp") }}
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
      <Select
        v-model="furnishing"
        :options="furnishingOptions"
        :label="t('owner.properties.detail.fields.furnishing')"
        :error="errors.furnishing"
      />
      <Select
        v-model="titleType"
        :options="titleTypeOptions"
        :label="t('owner.properties.detail.fields.titleType')"
        :error="errors.titleType"
      />
      <Input
        v-if="titleType === 'leasehold'"
        v-model="tenureExpiry"
        type="date"
        :label="t('owner.properties.detail.fields.tenureExpiry')"
        :error="errors.tenureExpiry"
      />
    </div>

    <label class="flex cursor-pointer items-center gap-2 pt-2">
      <input
        v-model="strataTitle"
        type="checkbox"
        class="h-4 w-4 rounded-xs border border-line-passive accent-ink"
      />
      <span class="text-body text-ink">
        {{ t("owner.properties.detail.fields.strataTitle") }}
      </span>
    </label>

    <div class="flex justify-end pt-2">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.properties.detail.save") }}
      </Button>
    </div>
  </form>
</template>
