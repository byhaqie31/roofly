<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { payoutAccountFormSchema, type PayoutAccountFormDto } from "~/schemas/payoutAccount";
import type { DuitNowIdType, MalaysianBank, PayoutAccount } from "~/types/payout";
import { BANKS } from "~/config/banks";
import { useToast } from "~/composables/useToast";
import Modal from "~/components/ui/Modal.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";

const props = withDefaults(
  defineProps<{
    open: boolean;
    account?: PayoutAccount | null;
  }>(),
  { account: null },
);

const emit = defineEmits<{
  "update:open": [value: boolean];
  saved: [account: PayoutAccount];
}>();

const { t } = useI18n();
const { show } = useToast();
const { toFieldErrors } = useApiError();
const submitting = ref(false);

const isEditMode = computed(() => !!props.account);

const buildInitialValues = (): PayoutAccountFormDto => ({
  label: props.account?.label ?? "",
  bank: props.account?.bank ?? "maybank",
  accountHolderName: props.account?.accountHolderName ?? "",
  accountNumber: props.account?.accountNumber ?? "",
  duitnowIdType: props.account?.duitnowIdType ?? "",
  duitnowId: props.account?.duitnowId ?? "",
});

const { defineField, handleSubmit, errors, resetForm, setErrors } = useForm<PayoutAccountFormDto>({
  validationSchema: toTypedSchema(payoutAccountFormSchema),
  initialValues: buildInitialValues(),
});

const [label] = defineField("label");
const [bank] = defineField("bank");
const [accountHolderName] = defineField("accountHolderName");
const [accountNumber] = defineField("accountNumber");
const [duitnowIdType] = defineField("duitnowIdType");
const [duitnowId] = defineField("duitnowId");

const bankOptions = computed(() =>
  BANKS.map((b) => ({ value: b.value, label: b.value === "other" ? t("owner.settings.payouts.bankOther") : b.label })),
);

// "" = no DuitNow ID — reka-ui's Select can't hold an empty value, so "none" stands in.
const duitnowTypeModel = computed({
  get: () => duitnowIdType.value || "none",
  set: (v: string) => {
    duitnowIdType.value = v === "none" ? "" : (v as DuitNowIdType);
    if (v === "none") duitnowId.value = "";
  },
});
const duitnowTypeOptions = computed(() => [
  { value: "none", label: t("owner.settings.payouts.duitnowTypes.none") },
  ...(["phone", "mykad", "brn", "passport"] as const).map((v) => ({
    value: v,
    label: t(`owner.settings.payouts.duitnowTypes.${v}`),
  })),
]);

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  const payload = {
    label: values.label,
    bank: values.bank as MalaysianBank,
    accountHolderName: values.accountHolderName,
    accountNumber: values.accountNumber ? values.accountNumber.replace(/\D/g, "") : null,
    duitnowIdType: values.duitnowIdType || null,
    duitnowId: values.duitnowIdType ? values.duitnowId || null : null,
  };
  try {
    const saved = isEditMode.value && props.account
      ? await usePayoutAccounts().update(props.account.id, payload)
      : await usePayoutAccounts().create(payload);
    show(isEditMode.value ? t("common.savedToast") : t("owner.settings.payouts.createdToast"), "success");
    emit("saved", saved);
    emit("update:open", false);
  } catch (err) {
    const fieldErrors = toFieldErrors(err);
    if (fieldErrors) {
      setErrors(fieldErrors);
      return;
    }
    show(t("common.genericError"), "danger");
  } finally {
    submitting.value = false;
  }
});

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) resetForm({ values: buildInitialValues() });
  },
);
</script>

<template>
  <Modal
    :open="open"
    :title="isEditMode ? t('owner.settings.payouts.editTitle') : t('owner.settings.payouts.addTitle')"
    :description="t('owner.settings.payouts.formHelp')"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form id="payout-account-form" class="space-y-4" @submit.prevent="onSubmit">
      <Input
        v-model="label"
        :label="t('owner.settings.payouts.fields.label')"
        :placeholder="t('owner.settings.payouts.placeholders.label')"
        :error="errors.label"
      />
      <Select
        v-model="bank"
        :options="bankOptions"
        :label="t('owner.settings.payouts.fields.bank')"
        :error="errors.bank"
      />
      <Input
        v-model="accountHolderName"
        :label="t('owner.settings.payouts.fields.accountHolderName')"
        :error="errors.accountHolderName"
      />
      <div>
        <Input
          v-model="accountNumber"
          :label="t('owner.settings.payouts.fields.accountNumber')"
          :error="errors.accountNumber"
        />
        <p class="mt-1.5 text-micro text-ink-faint">
          {{ t("owner.settings.payouts.accountNumberHint") }}
        </p>
      </div>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Select
          v-model="duitnowTypeModel"
          :options="duitnowTypeOptions"
          :label="t('owner.settings.payouts.fields.duitnowIdType')"
          :error="errors.duitnowIdType"
        />
        <Input
          v-model="duitnowId"
          :label="t('owner.settings.payouts.fields.duitnowId')"
          :disabled="!duitnowIdType"
          :error="errors.duitnowId"
        />
      </div>
    </form>

    <template #footer>
      <Button variant="ghost" :disabled="submitting" @click="emit('update:open', false)">
        {{ t("common.cancel") }}
      </Button>
      <Button type="submit" form="payout-account-form" variant="primary" :loading="submitting">
        {{ isEditMode ? t("common.save") : t("owner.settings.payouts.add") }}
      </Button>
    </template>
  </Modal>
</template>
