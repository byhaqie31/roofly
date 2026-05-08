<script setup lang="ts">
import { ref } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { propertyDetailsFormSchema } from "~/schemas/property";
import type { Property } from "~/types/property";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{ property: Property }>();
const emit = defineEmits<{ saved: [property: Property] }>();

const { t } = useI18n();
const submitting = ref(false);

const senToRinggit = (sen?: number) =>
  sen == null ? undefined : sen / 100;
const ringgitToSen = (rm?: number) =>
  rm == null ? undefined : Math.round(rm * 100);

const d = props.property.details ?? {};
const initialValues = {
  purchaseDate: d.purchaseDate,
  purchasePrice: senToRinggit(d.purchasePrice),
  monthlyMaintenanceFee: senToRinggit(d.monthlyMaintenanceFee),
  quitRentAnnual: senToRinggit(d.quitRentAnnual),
  assessmentRateAnnual: senToRinggit(d.assessmentRateAnnual),
  insurancePolicyNo: d.insurancePolicyNo,
  insuranceProvider: d.insuranceProvider,
  tnbAccountNo: d.tnbAccountNo,
  waterAccountNo: d.waterAccountNo,
  indahWaterAccountNo: d.indahWaterAccountNo,
  notes: d.notes,
};

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: toTypedSchema(propertyDetailsFormSchema),
  initialValues,
});

const [purchaseDate] = defineField("purchaseDate");
const [purchasePrice] = defineField("purchasePrice");
const [monthlyMaintenanceFee] = defineField("monthlyMaintenanceFee");
const [quitRentAnnual] = defineField("quitRentAnnual");
const [assessmentRateAnnual] = defineField("assessmentRateAnnual");
const [insurancePolicyNo] = defineField("insurancePolicyNo");
const [insuranceProvider] = defineField("insuranceProvider");
const [tnbAccountNo] = defineField("tnbAccountNo");
const [waterAccountNo] = defineField("waterAccountNo");
const [indahWaterAccountNo] = defineField("indahWaterAccountNo");
const [notes] = defineField("notes");

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const updated = await useProperties().update(props.property.id, {
      details: {
        purchaseDate: values.purchaseDate,
        purchasePrice: ringgitToSen(values.purchasePrice),
        monthlyMaintenanceFee: ringgitToSen(values.monthlyMaintenanceFee),
        quitRentAnnual: ringgitToSen(values.quitRentAnnual),
        assessmentRateAnnual: ringgitToSen(values.assessmentRateAnnual),
        insurancePolicyNo: values.insurancePolicyNo,
        insuranceProvider: values.insuranceProvider,
        tnbAccountNo: values.tnbAccountNo,
        waterAccountNo: values.waterAccountNo,
        indahWaterAccountNo: values.indahWaterAccountNo,
        notes: values.notes,
      },
    });
    emit("saved", updated);
  } finally {
    submitting.value = false;
  }
});
</script>

<template>
  <form class="space-y-4" @submit.prevent="onSubmit">
    <p class="text-caption text-ink-muted">
      {{ t("owner.properties.detail.costsHelp") }}
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Input
        v-model="purchaseDate"
        type="date"
        :label="t('owner.properties.detail.fields.purchaseDate')"
        :error="errors.purchaseDate"
      />
      <Input
        v-model="purchasePrice"
        type="number"
        step="0.01"
        :min="0"
        :label="t('owner.properties.detail.fields.purchasePrice')"
        :error="errors.purchasePrice"
      />
      <Input
        v-model="monthlyMaintenanceFee"
        type="number"
        step="0.01"
        :min="0"
        :label="t('owner.properties.detail.fields.monthlyMaintenanceFee')"
        :error="errors.monthlyMaintenanceFee"
      />
      <Input
        v-model="quitRentAnnual"
        type="number"
        step="0.01"
        :min="0"
        :label="t('owner.properties.detail.fields.quitRentAnnual')"
        :error="errors.quitRentAnnual"
      />
      <Input
        v-model="assessmentRateAnnual"
        type="number"
        step="0.01"
        :min="0"
        :label="t('owner.properties.detail.fields.assessmentRateAnnual')"
        :error="errors.assessmentRateAnnual"
      />
      <Input
        v-model="insurancePolicyNo"
        :label="t('owner.properties.detail.fields.insurancePolicyNo')"
        :error="errors.insurancePolicyNo"
      />
      <Input
        v-model="insuranceProvider"
        :label="t('owner.properties.detail.fields.insuranceProvider')"
        :error="errors.insuranceProvider"
      />
      <Input
        v-model="tnbAccountNo"
        :label="t('owner.properties.detail.fields.tnbAccountNo')"
        :error="errors.tnbAccountNo"
      />
      <Input
        v-model="waterAccountNo"
        :label="t('owner.properties.detail.fields.waterAccountNo')"
        :error="errors.waterAccountNo"
      />
      <Input
        v-model="indahWaterAccountNo"
        :label="t('owner.properties.detail.fields.indahWaterAccountNo')"
        :error="errors.indahWaterAccountNo"
      />
    </div>

    <label class="block">
      <span class="mb-1.5 block text-caption font-normal text-ink-strong">
        {{ t("owner.properties.detail.fields.notes") }}
      </span>
      <textarea
        v-model="notes"
        rows="4"
        class="w-full rounded-sm border border-line-passive bg-surface-page px-3 py-2 text-body outline-none transition focus:border-line-interactive focus:shadow-focus"
      />
      <span
        v-if="errors.notes"
        class="mt-1.5 block text-caption text-accent"
        role="alert"
      >
        {{ errors.notes }}
      </span>
    </label>

    <div class="flex justify-end pt-2">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.properties.detail.save") }}
      </Button>
    </div>
  </form>
</template>
