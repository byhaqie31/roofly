<script setup lang="ts">
import { computed, ref } from "vue";
import { useForm, useFieldArray } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import {
  DropdownMenuRoot,
  DropdownMenuTrigger,
  DropdownMenuPortal,
  DropdownMenuContent,
  DropdownMenuItem,
} from "reka-ui";
import { propertyOwnershipFormSchema } from "~/schemas/property";
import type { Property, PropertyCoOwner } from "~/types/property";
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
  // Co-owners live top-level on the property, but they're edited in this form.
  // On save we extract them and send a separate top-level patch.
  coOwners: props.property.coOwners.map((co) => ({ ...co })),
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

const { fields: coOwnerFields, push: pushCoOwner, remove: removeCoOwnerAt, update: updateCoOwner } =
  useFieldArray<PropertyCoOwner>("coOwners");

const addCoOwner = () =>
  pushCoOwner({
    id: crypto.randomUUID(),
    name: "",
    sharePct: 0,
    isPrimary: false,
  });

const removeCoOwner = (idx: number) => {
  const target = coOwnerFields.value[idx]?.value;
  // Block removing the primary; user must nominate a new primary first.
  if (target?.isPrimary) {
    show(t("owner.properties.detail.coOwners.cantRemovePrimary"), "danger");
    return;
  }
  removeCoOwnerAt(idx);
};

const setPrimary = (idx: number) => {
  coOwnerFields.value.forEach((field, i) => {
    const v = field.value as PropertyCoOwner;
    updateCoOwner(i, { ...v, isPrimary: i === idx });
  });
};

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

const primaryCount = computed(
  () => (values.coOwners ?? []).filter((c) => c?.isPrimary).length,
);

const coOwnersValid = computed(
  () => sharePctTotal.value === 100 && primaryCount.value === 1,
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
      },
      // Co-owners are top-level on Property — they go in a separate
      // `property_co_owners` table on the backend, not the ownership JSON.
      coOwners: vals.coOwners,
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

      <div class="space-y-3">
        <div
          v-for="(field, idx) in coOwnerFields"
          :key="field.key"
          class="rounded-md border border-line-passive p-3 sm:border-0 sm:p-0"
        >
          <div
            class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:gap-3"
          >
            <!-- Name field — label row pairs with the action on mobile. -->
            <div class="sm:min-w-[10rem] sm:flex-1">
              <div class="mb-1.5 flex items-center justify-between gap-2">
                <span class="text-caption font-normal text-ink-strong">
                  {{ t("owner.properties.detail.fields.coOwnerName") }}
                </span>
                <!-- Mobile: action beside the Co-owner label -->
                <DropdownMenuRoot>
                  <DropdownMenuTrigger as-child>
                    <button
                      type="button"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-sm text-ink-muted outline-none transition hover:bg-surface-hover hover:text-ink focus-visible:shadow-focus sm:hidden"
                      :aria-label="t('owner.properties.detail.coOwners.actionsAria')"
                    >
                      <Icon name="MoreVertical" :size="16" />
                    </button>
                  </DropdownMenuTrigger>
                  <DropdownMenuPortal>
                    <DropdownMenuContent
                      :side-offset="4"
                      align="end"
                      class="z-50 min-w-[10rem] rounded-md border border-line-passive bg-surface-raised p-1 shadow-modal"
                    >
                      <DropdownMenuItem
                        v-if="!(field.value as PropertyCoOwner).isPrimary"
                        class="flex cursor-pointer select-none items-center gap-2 rounded-sm px-3 py-2 text-caption text-ink-strong outline-none transition data-[highlighted]:bg-surface-hover"
                        @select="setPrimary(idx)"
                      >
                        <Icon name="Star" :size="14" />
                        {{ t("owner.properties.detail.coOwners.menuSetPrimary") }}
                      </DropdownMenuItem>
                      <DropdownMenuItem
                        class="flex cursor-pointer select-none items-center gap-2 rounded-sm px-3 py-2 text-caption text-ink-strong outline-none transition data-[highlighted]:bg-surface-hover"
                        @select="removeCoOwner(idx)"
                      >
                        <Icon name="Trash2" :size="14" />
                        {{ t("owner.properties.detail.coOwners.menuRemove") }}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenuPortal>
                </DropdownMenuRoot>
              </div>
              <Input
                v-model="(field.value as PropertyCoOwner).name"
                :label="undefined"
                :placeholder="t('owner.properties.detail.placeholders.coOwnerName')"
              >
                <template
                  v-if="(field.value as PropertyCoOwner).isPrimary"
                  #suffix
                >
                  <span
                    class="inline-flex items-center gap-1 rounded-pill bg-status-active-soft px-2 py-0.5 text-micro font-medium text-status-active"
                  >
                    <Icon name="Star" :size="12" />
                    {{ t("owner.properties.detail.coOwners.primaryLabel") }}
                  </span>
                </template>
              </Input>
            </div>

            <div class="sm:w-28">
              <Input
                v-model="(field.value as PropertyCoOwner).sharePct"
                type="number"
                :min="0"
                :max="100"
                :label="t('owner.properties.detail.fields.coOwnerSharePct')"
              />
            </div>

            <!-- Desktop: action at end of fields row, aligned to input bottom. -->
            <DropdownMenuRoot>
              <DropdownMenuTrigger as-child>
                <button
                  type="button"
                  class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-sm text-ink-muted outline-none transition hover:bg-surface-hover hover:text-ink focus-visible:shadow-focus sm:inline-flex"
                  :aria-label="t('owner.properties.detail.coOwners.actionsAria')"
                >
                  <Icon name="MoreVertical" :size="16" />
                </button>
              </DropdownMenuTrigger>
              <DropdownMenuPortal>
                <DropdownMenuContent
                  :side-offset="4"
                  align="end"
                  class="z-50 min-w-[10rem] rounded-md border border-line-passive bg-surface-raised p-1 shadow-modal"
                >
                  <DropdownMenuItem
                    v-if="!(field.value as PropertyCoOwner).isPrimary"
                    class="flex cursor-pointer select-none items-center gap-2 rounded-sm px-3 py-2 text-caption text-ink-strong outline-none transition data-[highlighted]:bg-surface-hover"
                    @select="setPrimary(idx)"
                  >
                    <Icon name="Star" :size="14" />
                    {{ t("owner.properties.detail.coOwners.menuSetPrimary") }}
                  </DropdownMenuItem>
                  <DropdownMenuItem
                    class="flex cursor-pointer select-none items-center gap-2 rounded-sm px-3 py-2 text-caption text-ink-strong outline-none transition data-[highlighted]:bg-surface-hover"
                    @select="removeCoOwner(idx)"
                  >
                    <Icon name="Trash2" :size="14" />
                    {{ t("owner.properties.detail.coOwners.menuRemove") }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenuPortal>
            </DropdownMenuRoot>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3">
        <Button variant="ghost" size="sm" @click="addCoOwner">
          + {{ t("owner.properties.detail.addCoOwner") }}
        </Button>
        <div class="flex items-center gap-4">
          <span
            :class="[
              'text-caption tabular-nums',
              sharePctTotal === 100 ? 'text-status-active' : 'text-status-maintenance',
            ]"
          >
            {{ t("owner.properties.detail.shareSum", { pct: sharePctTotal }) }}
          </span>
          <span
            v-if="primaryCount !== 1"
            class="text-caption text-status-maintenance"
          >
            {{ t("owner.properties.detail.coOwners.primaryRequired") }}
          </span>
        </div>
      </div>
    </section>

    <div class="flex flex-col items-end gap-2 pt-2">
      <p
        v-if="!coOwnersValid"
        class="text-caption text-status-maintenance"
      >
        {{ t("owner.properties.detail.coOwners.blockedSave") }}
      </p>
      <Button
        type="submit"
        variant="primary"
        :loading="submitting"
        :disabled="!coOwnersValid"
      >
        {{ t("owner.properties.detail.save") }}
      </Button>
    </div>
  </form>
</template>
