<script setup lang="ts">
import type { PayoutAccount } from "~/types/payout";
import { bankLabel } from "~/config/banks";
import { copyToClipboard } from "~/utils/clipboard";
import { useToast } from "~/composables/useToast";
import Icon from "~/components/ui/Icon.vue";

/**
 * Where the tenant sends rent (spec 2026-10-08): full details with Copy
 * buttons, since they're typed into the tenant's own banking app.
 */
const props = defineProps<{
  account: PayoutAccount;
  /** Shown as the suggested transfer reference (the invoice number). */
  reference?: string;
}>();

const { t } = useI18n();
const { show } = useToast();

const copy = async (value: string) => {
  const ok = await copyToClipboard(value);
  show(ok ? t("tenant.payTo.copied") : t("common.genericError"), ok ? "success" : "danger");
};

const rows = () =>
  [
    { key: "bank", label: t("tenant.payTo.bank"), value: bankLabel(props.account.bank), copyable: false },
    { key: "holder", label: t("tenant.payTo.holder"), value: props.account.accountHolderName, copyable: false },
    props.account.accountNumber
      ? { key: "number", label: t("tenant.payTo.accountNumber"), value: props.account.accountNumber, copyable: true }
      : null,
    props.account.duitnowId && props.account.duitnowIdType
      ? {
          key: "duitnow",
          label: t("tenant.payTo.duitnow", { type: t(`tenant.payTo.duitnowTypes.${props.account.duitnowIdType}`) }),
          value: props.account.duitnowId,
          copyable: true,
        }
      : null,
    props.reference
      ? { key: "reference", label: t("tenant.payTo.reference"), value: props.reference, copyable: true }
      : null,
  ].filter((r) => r !== null);
</script>

<template>
  <dl class="divide-y divide-line-passive rounded-md border border-line-passive bg-surface-page">
    <div
      v-for="row in rows()"
      :key="row.key"
      class="flex items-center justify-between gap-3 px-4 py-2.5"
    >
      <div class="min-w-0">
        <dt class="text-micro text-ink-muted">{{ row.label }}</dt>
        <dd class="break-all text-body tabular-nums text-ink">{{ row.value }}</dd>
      </div>
      <button
        v-if="row.copyable"
        type="button"
        class="inline-flex shrink-0 items-center gap-1 rounded-sm px-2 py-1 text-caption text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus"
        :aria-label="t('tenant.payTo.copyLabel', { what: row.label })"
        @click="copy(row.value)"
      >
        <Icon name="Copy" :size="14" />
        {{ t("tenant.payTo.copy") }}
      </button>
    </div>
  </dl>
</template>
