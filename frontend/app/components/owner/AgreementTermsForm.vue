<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { agreementFormSchema } from "~/schemas/agreement";
import type { Agreement, AgreementInput } from "~/types/agreement";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";
import { useToast } from "~/composables/useToast";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{
  agreement?: Agreement | null;
  mode: "create" | "edit";
}>();

const emit = defineEmits<{
  saved: [agreement: Agreement];
}>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);

const allProperties = ref<Property[]>([]);
const allUnits = ref<Unit[]>([]);
const allTenants = ref<Tenant[]>([]);
const formPropertyId = ref<string>("");

const senToRinggit = (sen: number) => sen / 100;
const ringgitToSen = (rm: number) => Math.round(rm * 100);

interface FormValues {
  unitId: string;
  tenantId: string;
  startDate: string;
  endDate: string;
  rentAmount: number;
  depositAmount: number;
  lateFee: number;
  rentDueDay: number;
  status: Agreement["status"];
}

const buildInitialValues = (): FormValues => {
  const a = props.agreement;
  return {
    unitId: a?.unitId ?? "",
    tenantId: a?.tenantId ?? "",
    startDate: a?.startDate ?? "",
    endDate: a?.endDate ?? "",
    rentAmount: a ? senToRinggit(a.rentAmount) : 0,
    depositAmount: a ? senToRinggit(a.depositAmount) : 0,
    lateFee: a ? senToRinggit(a.lateFee) : 0,
    rentDueDay: a?.rentDueDay ?? 1,
    status: a?.status ?? "draft",
  };
};

const { defineField, handleSubmit, errors, resetForm, setFieldValue } =
  useForm<FormValues>({
    validationSchema: toTypedSchema(agreementFormSchema),
    initialValues: buildInitialValues(),
  });

const [unitId] = defineField("unitId");
const [tenantId] = defineField("tenantId");
const [startDate] = defineField("startDate");
const [endDate] = defineField("endDate");
const [rentAmount] = defineField("rentAmount");
const [depositAmount] = defineField("depositAmount");
const [lateFee] = defineField("lateFee");
const [rentDueDay] = defineField("rentDueDay");
const [status] = defineField("status");

const propertyOptions = computed(() =>
  allProperties.value.map((p) => ({ value: p.id, label: p.name })),
);

const unitOptions = computed(() =>
  allUnits.value
    .filter((u) => !formPropertyId.value || u.propertyId === formPropertyId.value)
    .map((u) => ({ value: u.id, label: u.label })),
);

const tenantOptions = computed(() =>
  allTenants.value.map((tn) => ({ value: tn.id, label: tn.name })),
);

const statusOptions = computed(() => [
  { value: "draft", label: t("owner.agreements.status.draft") },
  { value: "active", label: t("owner.agreements.status.active") },
  { value: "expired", label: t("owner.agreements.status.expired") },
  { value: "terminated", label: t("owner.agreements.status.terminated") },
]);

const onPropertyChange = (newPropertyId: string) => {
  formPropertyId.value = newPropertyId;
  if (
    unitId.value &&
    !allUnits.value.some(
      (u) => u.id === unitId.value && u.propertyId === newPropertyId,
    )
  ) {
    setFieldValue("unitId", "");
  }
};

onMounted(async () => {
  [allProperties.value, allUnits.value, allTenants.value] = await Promise.all([
    useProperties().list(),
    useUnits().list(),
    useTenants().list(),
  ]);
  resetForm({ values: buildInitialValues() });
  if (props.agreement) {
    const u = allUnits.value.find((x) => x.id === props.agreement!.unitId);
    formPropertyId.value = u?.propertyId ?? "";
  }
});

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const payload: AgreementInput = {
      unitId: values.unitId,
      tenantId: values.tenantId,
      startDate: values.startDate,
      endDate: values.endDate,
      rentAmount: ringgitToSen(values.rentAmount),
      depositAmount: ringgitToSen(values.depositAmount),
      lateFee: ringgitToSen(values.lateFee),
      rentDueDay: values.rentDueDay,
      status: values.status,
    };
    if (props.mode === "edit" && props.agreement) {
      const updated = await useAgreements().update(props.agreement.id, payload);
      show(t("common.savedToast"), "success");
      emit("saved", updated);
    } else {
      const created = await useAgreements().create(payload);
      show(t("owner.agreements.createdToast"), "success");
      emit("saved", created);
    }
  } finally {
    submitting.value = false;
  }
});
</script>

<template>
  <form class="space-y-5" @submit.prevent="onSubmit">
    <p class="text-caption text-ink-muted">
      {{ t("owner.agreements.detail.termsHelp") }}
    </p>

    <section class="space-y-3">
      <h3
        class="text-caption font-semibold uppercase tracking-wide text-ink-muted"
      >
        {{ t("owner.agreements.detail.sections.parties") }}
      </h3>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Select
          :model-value="formPropertyId"
          :options="propertyOptions"
          :label="t('owner.agreements.fields.property')"
          :placeholder="t('owner.agreements.placeholders.property')"
          @update:model-value="onPropertyChange"
        />
        <Select
          v-model="unitId"
          :options="unitOptions"
          :label="t('owner.agreements.fields.unit')"
          :placeholder="
            formPropertyId
              ? t('owner.agreements.placeholders.unit')
              : t('owner.agreements.placeholders.unitDisabled')
          "
          :disabled="!formPropertyId"
          :error="errors.unitId"
        />
      </div>
      <Select
        v-model="tenantId"
        :options="tenantOptions"
        :label="t('owner.agreements.fields.tenant')"
        :placeholder="t('owner.agreements.placeholders.tenant')"
        :error="errors.tenantId"
      />
    </section>

    <section class="space-y-3">
      <h3
        class="text-caption font-semibold uppercase tracking-wide text-ink-muted"
      >
        {{ t("owner.agreements.detail.sections.term") }}
      </h3>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input
          v-model="startDate"
          type="date"
          :label="t('owner.agreements.fields.startDate')"
          :error="errors.startDate"
        />
        <Input
          v-model="endDate"
          type="date"
          :label="t('owner.agreements.fields.endDate')"
          :error="errors.endDate"
        />
      </div>
    </section>

    <section class="space-y-3">
      <h3
        class="text-caption font-semibold uppercase tracking-wide text-ink-muted"
      >
        {{ t("owner.agreements.detail.sections.money") }}
      </h3>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Input
          v-model="rentAmount"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.agreements.fields.rentAmount')"
          :error="errors.rentAmount"
        />
        <Input
          v-model="depositAmount"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.agreements.fields.depositAmount')"
          :error="errors.depositAmount"
        />
        <Input
          v-model="lateFee"
          type="number"
          step="0.01"
          :min="0"
          :label="t('owner.agreements.fields.lateFee')"
          :error="errors.lateFee"
        />
      </div>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input
          v-model="rentDueDay"
          type="number"
          :min="1"
          :max="28"
          :label="t('owner.agreements.fields.rentDueDay')"
          :error="errors.rentDueDay"
        />
        <Select
          v-if="mode === 'edit'"
          v-model="status"
          :options="statusOptions"
          :label="t('owner.agreements.fields.status')"
          :error="errors.status"
        />
      </div>
    </section>

    <div class="flex justify-end pt-2">
      <Button type="submit" variant="primary" :loading="submitting">
        {{
          mode === "edit"
            ? t("owner.agreements.detail.save")
            : t("owner.agreements.add")
        }}
      </Button>
    </div>
  </form>
</template>
