<script setup lang="ts">
import { computed, ref } from "vue";
import { useForm, useFieldArray } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { propertyOwnershipFormSchema } from "~/schemas/property";
import type { Property } from "~/types/property";
import { useToast } from "~/composables/useToast";
import { computeCapitalGains } from "~/utils/rpgt";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";
import Pill from "~/components/ui/Pill.vue";

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

const o = props.property.ownership ?? {};
const m = o.mortgage ?? {};

const initialValues = {
  titleType: o.titleType,
  titleNumber: o.titleNumber ?? "",
  lotNumber: o.lotNumber ?? "",
  tenureExpiry: o.tenureExpiry ?? "",
  strataTitle: o.strataTitle,
  masterTitle: o.masterTitle,
  purchaseDate: o.purchaseDate ?? "",
  purchasePrice: senToRinggit(o.purchasePrice),
  stampDuty: senToRinggit(o.stampDuty),
  legalFees: senToRinggit(o.legalFees),
  currentMarketValue: senToRinggit(o.currentMarketValue),
  lastValuedAt: o.lastValuedAt ?? "",
  valuationSource: o.valuationSource,
  mortgage: {
    bank: m.bank ?? "",
    loanAmount: senToRinggit(m.loanAmount),
    outstandingBalance: senToRinggit(m.outstandingBalance),
    monthlyInstalment: senToRinggit(m.monthlyInstalment),
    tenureYears: m.tenureYears,
    maturityDate: m.maturityDate ?? "",
    interestRatePct: m.interestRatePct,
  },
  coOwners: (o.coOwners ?? []).map((co) => ({ ...co })),
};

const { defineField, handleSubmit, errors, values } = useForm({
  validationSchema: toTypedSchema(propertyOwnershipFormSchema),
  initialValues,
});

const [titleType] = defineField("titleType");
const [titleNumber] = defineField("titleNumber");
const [lotNumber] = defineField("lotNumber");
const [tenureExpiry] = defineField("tenureExpiry");
const [strataTitle] = defineField("strataTitle");
const [masterTitle] = defineField("masterTitle");
const [purchaseDate] = defineField("purchaseDate");
const [purchasePrice] = defineField("purchasePrice");
const [stampDuty] = defineField("stampDuty");
const [legalFees] = defineField("legalFees");
const [currentMarketValue] = defineField("currentMarketValue");
const [lastValuedAt] = defineField("lastValuedAt");
const [valuationSource] = defineField("valuationSource");
const [mortgageBank] = defineField("mortgage.bank");
const [mortgageLoanAmount] = defineField("mortgage.loanAmount");
const [mortgageOutstanding] = defineField("mortgage.outstandingBalance");
const [mortgageInstalment] = defineField("mortgage.monthlyInstalment");
const [mortgageTenure] = defineField("mortgage.tenureYears");
const [mortgageMaturity] = defineField("mortgage.maturityDate");
const [mortgageRate] = defineField("mortgage.interestRatePct");

const { fields: coOwnerFields, push: addCoOwner, remove: removeCoOwner } =
  useFieldArray<{ name: string; sharePct: number }>("coOwners");

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

const valuationSourceOptions = computed(() => [
  { value: "bank", label: t("owner.properties.detail.valuationSources.bank") },
  { value: "agent", label: t("owner.properties.detail.valuationSources.agent") },
  { value: "self", label: t("owner.properties.detail.valuationSources.self") },
]);

const acquisitionTotal = computed(() => {
  const p = (values.purchasePrice ?? 0) + (values.stampDuty ?? 0) + (values.legalFees ?? 0);
  return ringgitToSen(p) ?? 0;
});

const sharePctTotal = computed(() =>
  (values.coOwners ?? []).reduce(
    (sum, co) => sum + (co?.sharePct ?? 0),
    0,
  ),
);

const gainsSnapshot = computed(() =>
  computeCapitalGains({
    purchasePrice: ringgitToSen(values.purchasePrice),
    stampDuty: ringgitToSen(values.stampDuty),
    legalFees: ringgitToSen(values.legalFees),
    currentMarketValue: ringgitToSen(values.currentMarketValue),
    purchaseDate: values.purchaseDate || undefined,
  }),
);

const onSubmit = handleSubmit(async (vals) => {
  submitting.value = true;
  try {
    const updated = await useProperties().update(props.property.id, {
      ownership: {
        titleType: vals.titleType,
        titleNumber: vals.titleNumber || undefined,
        lotNumber: vals.lotNumber || undefined,
        tenureExpiry: vals.tenureExpiry || undefined,
        strataTitle: vals.strataTitle,
        masterTitle: vals.masterTitle,
        purchaseDate: vals.purchaseDate || undefined,
        purchasePrice: ringgitToSen(vals.purchasePrice),
        stampDuty: ringgitToSen(vals.stampDuty),
        legalFees: ringgitToSen(vals.legalFees),
        currentMarketValue: ringgitToSen(vals.currentMarketValue),
        lastValuedAt: vals.lastValuedAt || undefined,
        valuationSource: vals.valuationSource,
        mortgage:
          vals.mortgage && Object.values(vals.mortgage).some((v) => v != null && v !== "")
            ? {
                bank: vals.mortgage.bank || undefined,
                loanAmount: ringgitToSen(vals.mortgage.loanAmount),
                outstandingBalance: ringgitToSen(vals.mortgage.outstandingBalance),
                monthlyInstalment: ringgitToSen(vals.mortgage.monthlyInstalment),
                tenureYears: vals.mortgage.tenureYears,
                maturityDate: vals.mortgage.maturityDate || undefined,
                interestRatePct: vals.mortgage.interestRatePct,
              }
            : undefined,
        coOwners: (vals.coOwners ?? []).length > 0 ? vals.coOwners : undefined,
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
      {{ t("owner.properties.detail.ownershipHelp") }}
    </p>

    <section class="space-y-4">
      <h3 :class="sectionHeading">
        {{ t("owner.properties.detail.sections.title") }}
      </h3>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Select
          v-model="titleType"
          :options="titleTypeOptions"
          :label="t('owner.properties.detail.fields.titleType')"
          :error="errors.titleType"
        />
        <Input
          v-model="titleNumber"
          :label="t('owner.properties.detail.fields.titleNumber')"
          :placeholder="t('owner.properties.detail.placeholders.titleNumber')"
          :error="errors.titleNumber"
        />
        <Input
          v-model="lotNumber"
          :label="t('owner.properties.detail.fields.lotNumber')"
          :error="errors.lotNumber"
        />
        <Input
          v-if="titleType === 'leasehold'"
          v-model="tenureExpiry"
          type="date"
          :label="t('owner.properties.detail.fields.tenureExpiry')"
          :error="errors.tenureExpiry"
        />
      </div>
      <div class="flex flex-wrap gap-6 pt-1">
        <label class="flex cursor-pointer items-center gap-2">
          <input
            v-model="strataTitle"
            type="checkbox"
            class="h-4 w-4 rounded-xs border border-line-passive accent-ink"
          />
          <span class="text-body text-ink">
            {{ t("owner.properties.detail.fields.strataTitle") }}
          </span>
        </label>
        <label class="flex cursor-pointer items-center gap-2">
          <input
            v-model="masterTitle"
            type="checkbox"
            class="h-4 w-4 rounded-xs border border-line-passive accent-ink"
          />
          <span class="text-body text-ink">
            {{ t("owner.properties.detail.fields.masterTitle") }}
          </span>
        </label>
      </div>
    </section>

    <section class="space-y-4">
      <h3 :class="sectionHeading">
        {{ t("owner.properties.detail.sections.acquisition") }}
      </h3>
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
          v-model="stampDuty"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.properties.detail.fields.stampDuty')"
          :error="errors.stampDuty"
        />
        <Input
          v-model="legalFees"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.properties.detail.fields.legalFees')"
          :error="errors.legalFees"
        />
      </div>
      <p class="text-caption text-ink-muted">
        {{ t("owner.properties.detail.acquisitionTotal") }}:
        <span class="ml-1 font-semibold text-ink tabular-nums">
          {{ formatRM(acquisitionTotal) }}
        </span>
      </p>
    </section>

    <section class="space-y-4">
      <h3 :class="sectionHeading">
        {{ t("owner.properties.detail.sections.valuation") }}
      </h3>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Input
          v-model="currentMarketValue"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.properties.detail.fields.currentMarketValue')"
          :error="errors.currentMarketValue"
        />
        <Input
          v-model="lastValuedAt"
          type="date"
          :label="t('owner.properties.detail.fields.lastValuedAt')"
          :error="errors.lastValuedAt"
        />
        <Select
          v-model="valuationSource"
          :options="valuationSourceOptions"
          :label="t('owner.properties.detail.fields.valuationSource')"
          :error="errors.valuationSource"
        />
      </div>
    </section>

    <section
      v-if="gainsSnapshot"
      class="rounded-md border border-line-passive bg-surface-page p-4"
    >
      <h3 class="mb-3 flex items-center gap-2 text-caption font-semibold uppercase tracking-wide text-ink-muted">
        {{ t("owner.properties.detail.gainsSnapshot") }}
        <Pill tone="neutral" class="font-normal normal-case tracking-normal">
          ~{{ Math.floor(gainsSnapshot.yearsHeld) }}y held ·
          {{ Math.round(gainsSnapshot.ratePct * 100) }}% RPGT
        </Pill>
      </h3>
      <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-body text-ink sm:grid-cols-4">
        <div>
          <dt class="text-caption text-ink-muted">
            {{ t("owner.properties.detail.gains.gain") }}
          </dt>
          <dd class="tabular-nums">{{ formatRM(gainsSnapshot.gain) }}</dd>
        </div>
        <div>
          <dt class="text-caption text-ink-muted">
            {{ t("owner.properties.detail.gains.rpgt") }}
          </dt>
          <dd class="tabular-nums">−{{ formatRM(gainsSnapshot.rpgt) }}</dd>
        </div>
        <div>
          <dt class="text-caption text-ink-muted">
            {{ t("owner.properties.detail.gains.net") }}
          </dt>
          <dd class="font-semibold tabular-nums">
            {{ formatRM(gainsSnapshot.net) }}
          </dd>
        </div>
        <div>
          <dt class="text-caption text-ink-muted">
            {{ t("owner.properties.detail.gains.cost") }}
          </dt>
          <dd class="tabular-nums">
            {{ formatRM(gainsSnapshot.acquisitionCost) }}
          </dd>
        </div>
      </dl>
      <p class="mt-3 text-micro text-ink-faint">
        {{ t("owner.properties.detail.gains.disclaimer") }}
      </p>
    </section>

    <section class="space-y-4">
      <h3 :class="sectionHeading">
        {{ t("owner.properties.detail.sections.mortgage") }}
      </h3>
      <Input
        v-model="mortgageBank"
        :label="t('owner.properties.detail.fields.mortgageBank')"
        :placeholder="t('owner.properties.detail.placeholders.mortgageBank')"
      />
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Input
          v-model="mortgageLoanAmount"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.properties.detail.fields.loanAmount')"
        />
        <Input
          v-model="mortgageOutstanding"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.properties.detail.fields.outstandingBalance')"
        />
        <Input
          v-model="mortgageInstalment"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.properties.detail.fields.monthlyInstalment')"
        />
        <Input
          v-model="mortgageTenure"
          type="number"
          :min="0"
          :max="40"
          :label="t('owner.properties.detail.fields.tenureYears')"
        />
        <Input
          v-model="mortgageMaturity"
          type="date"
          :label="t('owner.properties.detail.fields.maturityDate')"
        />
        <Input
          v-model="mortgageRate"
          type="number"
          step="0.01"
          :min="0"
          :max="20"
          :label="t('owner.properties.detail.fields.interestRatePct')"
        />
      </div>
    </section>

    <section class="space-y-3">
      <h3 :class="sectionHeading">
        {{ t("owner.properties.detail.sections.coOwners") }}
      </h3>
      <p class="text-caption text-ink-muted">
        {{ t("owner.properties.detail.coOwnersHelp") }}
      </p>

      <div
        v-for="(field, idx) in coOwnerFields"
        :key="field.key"
        class="flex flex-wrap items-end gap-3"
      >
        <div class="min-w-[12rem] flex-1">
          <Input
            v-model="(field.value as { name: string }).name"
            :label="
              idx === 0 ? t('owner.properties.detail.fields.coOwnerName') : undefined
            "
            :placeholder="t('owner.properties.detail.placeholders.coOwnerName')"
          />
        </div>
        <div class="w-32">
          <Input
            v-model="(field.value as { sharePct: number }).sharePct"
            type="number"
            :min="0"
            :max="100"
            :label="
              idx === 0
                ? t('owner.properties.detail.fields.coOwnerSharePct')
                : undefined
            "
          />
        </div>
        <Button
          variant="ghost"
          size="sm"
          @click="removeCoOwner(idx)"
        >
          <Icon name="X" :size="14" />
        </Button>
      </div>

      <div class="flex items-center justify-between gap-3">
        <Button
          variant="ghost"
          size="sm"
          @click="addCoOwner({ name: '', sharePct: 0 })"
        >
          + {{ t("owner.properties.detail.addCoOwner") }}
        </Button>
        <span
          v-if="coOwnerFields.length > 0"
          :class="[
            'text-caption tabular-nums',
            sharePctTotal === 100 ? 'text-status-active' : 'text-status-maintenance',
          ]"
        >
          {{ t("owner.properties.detail.shareSum", { pct: sharePctTotal }) }}
        </span>
      </div>
    </section>

    <div class="flex justify-end pt-2">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.properties.detail.save") }}
      </Button>
    </div>
  </form>
</template>
