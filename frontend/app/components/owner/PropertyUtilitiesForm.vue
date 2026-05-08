<script setup lang="ts">
import { computed, ref } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { propertyUtilitiesFormSchema } from "~/schemas/property";
import type { Property } from "~/types/property";
import { useToast } from "~/composables/useToast";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{ property: Property }>();
const emit = defineEmits<{ saved: [property: Property] }>();

const { t } = useI18n();
const { show } = useToast();
const { formatRM } = useMoney();
const submitting = ref(false);

const senToRinggit = (sen?: number) =>
  sen == null ? undefined : sen / 100;
const ringgitToSen = (rm?: number) =>
  rm == null ? undefined : Math.round(rm * 100);

const u = props.property.utilities ?? {};

const initialValues = {
  monthlyMaintenanceFee: senToRinggit(u.monthlyMaintenanceFee),
  sinkingFund: senToRinggit(u.sinkingFund),
  quitRentAnnual: senToRinggit(u.quitRentAnnual),
  assessmentRateAnnual: senToRinggit(u.assessmentRateAnnual),
  buildingInsuranceAnnual: senToRinggit(u.buildingInsuranceAnnual),
  tnbAccountNo: u.tnbAccountNo ?? "",
  waterAccountNo: u.waterAccountNo ?? "",
  indahWaterAccountNo: u.indahWaterAccountNo ?? "",
  internetAccountNo: u.internetAccountNo ?? "",
  managementCorpName: u.managementCorpName ?? "",
  managementCorpPhone: u.managementCorpPhone ?? "",
};

const { defineField, handleSubmit, errors, values } = useForm({
  validationSchema: toTypedSchema(propertyUtilitiesFormSchema),
  initialValues,
});

const [monthlyMaintenanceFee] = defineField("monthlyMaintenanceFee");
const [sinkingFund] = defineField("sinkingFund");
const [quitRentAnnual] = defineField("quitRentAnnual");
const [assessmentRateAnnual] = defineField("assessmentRateAnnual");
const [buildingInsuranceAnnual] = defineField("buildingInsuranceAnnual");
const [tnbAccountNo] = defineField("tnbAccountNo");
const [waterAccountNo] = defineField("waterAccountNo");
const [indahWaterAccountNo] = defineField("indahWaterAccountNo");
const [internetAccountNo] = defineField("internetAccountNo");
const [managementCorpName] = defineField("managementCorpName");
const [managementCorpPhone] = defineField("managementCorpPhone");

const annualTotal = computed(() => {
  const monthly =
    (values.monthlyMaintenanceFee ?? 0) + (values.sinkingFund ?? 0);
  const annual =
    (values.quitRentAnnual ?? 0) +
    (values.assessmentRateAnnual ?? 0) +
    (values.buildingInsuranceAnnual ?? 0);
  return ringgitToSen(monthly * 12 + annual) ?? 0;
});

const monthlyEquivalent = computed(() =>
  Math.round(annualTotal.value / 12),
);

const onSubmit = handleSubmit(async (vals) => {
  submitting.value = true;
  try {
    const updated = await useProperties().update(props.property.id, {
      utilities: {
        monthlyMaintenanceFee: ringgitToSen(vals.monthlyMaintenanceFee),
        sinkingFund: ringgitToSen(vals.sinkingFund),
        quitRentAnnual: ringgitToSen(vals.quitRentAnnual),
        assessmentRateAnnual: ringgitToSen(vals.assessmentRateAnnual),
        buildingInsuranceAnnual: ringgitToSen(vals.buildingInsuranceAnnual),
        tnbAccountNo: vals.tnbAccountNo || undefined,
        waterAccountNo: vals.waterAccountNo || undefined,
        indahWaterAccountNo: vals.indahWaterAccountNo || undefined,
        internetAccountNo: vals.internetAccountNo || undefined,
        managementCorpName: vals.managementCorpName || undefined,
        managementCorpPhone: vals.managementCorpPhone || undefined,
      },
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
    <p class="text-caption text-ink-muted">
      {{ t("owner.properties.detail.utilitiesHelp") }}
    </p>

    <section class="space-y-4">
      <h3 :class="sectionHeading">
        {{ t("owner.properties.detail.sections.recurringFees") }}
      </h3>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input
          v-model="monthlyMaintenanceFee"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.properties.detail.fields.monthlyMaintenanceFee')"
          :error="errors.monthlyMaintenanceFee"
        />
        <Input
          v-model="sinkingFund"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.properties.detail.fields.sinkingFund')"
          :error="errors.sinkingFund"
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
          v-model="buildingInsuranceAnnual"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.properties.detail.fields.buildingInsuranceAnnual')"
          :error="errors.buildingInsuranceAnnual"
        />
      </div>

      <div
        class="flex flex-wrap items-baseline justify-between gap-3 rounded-md border border-line-passive bg-surface-page px-4 py-3"
      >
        <span class="text-caption text-ink-muted">
          {{ t("owner.properties.detail.utilitiesAnnual") }}
        </span>
        <span class="text-card-title font-semibold text-ink tabular-nums">
          {{ formatRM(annualTotal) }}
        </span>
        <span class="basis-full text-caption text-ink-muted sm:basis-auto">
          {{ t("owner.properties.detail.utilitiesMonthly") }}:
          <span class="text-ink tabular-nums">
            {{ formatRM(monthlyEquivalent) }}
          </span>
        </span>
      </div>
    </section>

    <section class="space-y-4">
      <h3 :class="sectionHeading">
        {{ t("owner.properties.detail.sections.serviceAccounts") }}
      </h3>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
        <Input
          v-model="internetAccountNo"
          :label="t('owner.properties.detail.fields.internetAccountNo')"
          :error="errors.internetAccountNo"
        />
        <Input
          v-model="managementCorpName"
          :label="t('owner.properties.detail.fields.managementCorpName')"
          :error="errors.managementCorpName"
        />
        <Input
          v-model="managementCorpPhone"
          :label="t('owner.properties.detail.fields.managementCorpPhone')"
          :error="errors.managementCorpPhone"
        />
      </div>
    </section>

    <div class="flex justify-end pt-2">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.properties.detail.save") }}
      </Button>
    </div>
  </form>
</template>
