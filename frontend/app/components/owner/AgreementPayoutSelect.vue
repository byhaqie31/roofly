<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import type { PayoutAccount } from "~/types/payout";
import { bankLabel } from "~/config/banks";
import { maskAccountNumber } from "~/utils/paymentClaim";
import Select from "~/components/ui/Select.vue";

/**
 * Which payout account an agreement's rent goes to (spec 2026-10-08 § 3.2).
 * `null` = "Use my default" — the agreement follows whatever the default is.
 */
const props = defineProps<{
  modelValue: string | null;
  label?: string;
  error?: string;
}>();

const emit = defineEmits<{
  "update:modelValue": [value: string | null];
}>();

const { t } = useI18n();
const accounts = ref<PayoutAccount[]>([]);
const loaded = ref(false);

onMounted(async () => {
  try {
    accounts.value = await usePayoutAccounts().list();
  } finally {
    loaded.value = true;
  }
});

const DEFAULT = "default";
const describe = (a: PayoutAccount) =>
  [a.label, bankLabel(a.bank), maskAccountNumber(a.accountNumber)].filter(Boolean).join(" · ");

const options = computed(() => {
  const fallback = accounts.value.find((a) => a.isDefault);
  return [
    {
      value: DEFAULT,
      label: fallback
        ? t("owner.agreements.payout.useDefaultNamed", { label: fallback.label })
        : t("owner.agreements.payout.useDefault"),
    },
    ...accounts.value.map((a) => ({ value: a.id, label: describe(a) })),
  ];
});

const model = computed({
  get: () => props.modelValue ?? DEFAULT,
  set: (v: string) => emit("update:modelValue", v === DEFAULT ? null : v),
});
</script>

<template>
  <div>
    <Select
      :key="options.length"
      v-model="model"
      :options="options"
      :label="label"
      :error="error"
      :disabled="!loaded || accounts.length === 0"
    />
    <p v-if="loaded && accounts.length === 0" class="mt-1.5 text-micro text-ink-muted">
      {{ t("owner.agreements.payout.none") }}
      <NuxtLink to="/owner/settings?tab=payouts" class="text-ink underline underline-offset-2">
        {{ t("owner.agreements.payout.addLink") }}
      </NuxtLink>
    </p>
  </div>
</template>
