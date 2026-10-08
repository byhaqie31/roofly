<script setup lang="ts">
import { computed, ref, watch } from "vue";
import type { Payment } from "~/types/payment";
import type { PayoutAccount } from "~/types/payout";
import { bankLabel } from "~/config/banks";
import { maskAccountNumber } from "~/utils/paymentClaim";
import { useToast } from "~/composables/useToast";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";
import Icon from "~/components/ui/Icon.vue";

/**
 * Owner reviews a tenant's "I've paid" (spec 2026-10-08 § 4): check the bank
 * statement, then Confirm (invoice → paid) or Reject with a reason the tenant sees.
 */
const props = defineProps<{
  claim: Payment;
  payoutAccount: PayoutAccount | null;
}>();

const emit = defineEmits<{ resolved: [] }>();

const { t } = useI18n();
const { show } = useToast();
const { formatRM } = useMoney();
const { public: { features } } = useRuntimeConfig();

const mode = ref<"review" | "reject">("review");
const confirmDate = ref("");
const reason = ref("");
const reasonError = ref("");
const busy = ref(false);

watch(
  () => props.claim.id,
  () => {
    mode.value = "review";
    confirmDate.value = props.claim.paidAt.slice(0, 10);
    reason.value = "";
    reasonError.value = "";
  },
  { immediate: true },
);

const formatDate = (iso: string) => {
  const [y, m, d] = iso.slice(0, 10).split("-");
  return `${d}/${m}/${y}`;
};

// Only name the account when the claim was made against it.
const account = computed(() =>
  props.payoutAccount && props.payoutAccount.id === props.claim.payoutAccountId ? props.payoutAccount : null,
);

const onConfirm = async () => {
  busy.value = true;
  try {
    const changedDate = confirmDate.value && confirmDate.value !== props.claim.paidAt.slice(0, 10);
    await useInvoices().confirmClaim(props.claim.id, changedDate ? { paidAt: confirmDate.value } : {});
    show(t("owner.payments.claim.confirmedToast"), "success");
    emit("resolved");
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    busy.value = false;
  }
};

const onReject = async () => {
  const trimmed = reason.value.trim();
  if (trimmed.length < 3) {
    reasonError.value = t("owner.payments.claim.reasonRequired");
    return;
  }
  busy.value = true;
  try {
    await useInvoices().rejectClaim(props.claim.id, trimmed);
    show(t("owner.payments.claim.rejectedToast"), "success");
    emit("resolved");
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    busy.value = false;
  }
};
</script>

<template>
  <section class="space-y-4 rounded-md border border-status-pending bg-status-pending-soft p-4" role="status">
    <header class="flex items-start gap-3">
      <Icon name="BadgeCheck" :size="18" class="mt-0.5 shrink-0 text-status-pending" />
      <div>
        <h3 class="text-body font-semibold text-ink">{{ t("owner.payments.claim.title") }}</h3>
        <p class="mt-0.5 text-caption text-ink-muted">{{ t("owner.payments.claim.help") }}</p>
      </div>
    </header>

    <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-caption sm:grid-cols-2">
      <div>
        <dt class="text-ink-muted">{{ t("owner.payments.claim.amount") }}</dt>
        <dd class="text-body tabular-nums text-ink">{{ formatRM(claim.amount) }}</dd>
      </div>
      <div>
        <dt class="text-ink-muted">{{ t("owner.payments.claim.paidAt") }}</dt>
        <dd class="text-body tabular-nums text-ink">{{ formatDate(claim.paidAt) }}</dd>
      </div>
      <div>
        <dt class="text-ink-muted">{{ t("owner.payments.claim.reference") }}</dt>
        <dd class="text-body text-ink break-all">{{ claim.reference || "—" }}</dd>
      </div>
      <div v-if="account">
        <dt class="text-ink-muted">{{ t("owner.payments.claim.sentTo") }}</dt>
        <dd class="text-body text-ink">
          {{ account.label }}
          <span class="text-caption text-ink-muted tabular-nums">
            · {{ [bankLabel(account.bank), maskAccountNumber(account.accountNumber)].filter(Boolean).join(" · ") }}
          </span>
        </dd>
      </div>
      <div v-if="claim.note" class="sm:col-span-2">
        <dt class="text-ink-muted">{{ t("owner.payments.claim.note") }}</dt>
        <dd class="text-body text-ink">“{{ claim.note }}”</dd>
      </div>
    </dl>

    <p v-if="features.documents" class="flex items-center gap-2 text-micro text-ink-faint">
      <Icon name="Paperclip" :size="14" />
      {{ t("owner.payments.claim.receiptPlaceholder") }}
    </p>

    <div v-if="mode === 'review'" class="flex flex-col gap-3 border-t border-line-passive pt-4 sm:flex-row sm:items-end sm:justify-between">
      <div class="sm:w-48">
        <Input v-model="confirmDate" type="date" :label="t('owner.payments.claim.confirmDate')" />
      </div>
      <div class="flex gap-2">
        <Button variant="ghost" size="sm" :disabled="busy" @click="mode = 'reject'">
          {{ t("owner.payments.claim.reject") }}
        </Button>
        <Button variant="primary" size="sm" :loading="busy" @click="onConfirm">
          {{ t("owner.payments.claim.confirm") }}
        </Button>
      </div>
    </div>

    <div v-else class="space-y-3 border-t border-line-passive pt-4">
      <label class="block">
        <span class="mb-1.5 block text-caption text-ink-strong">{{ t("owner.payments.claim.reasonLabel") }}</span>
        <textarea
          v-model="reason"
          rows="3"
          maxlength="300"
          :placeholder="t('owner.payments.claim.reasonPlaceholder')"
          class="w-full rounded-sm border border-line-passive bg-surface-page px-3 py-2 text-body text-ink outline-none transition focus:border-line-interactive focus:shadow-focus"
        />
        <span v-if="reasonError" class="mt-1.5 block text-caption text-accent" role="alert">{{ reasonError }}</span>
      </label>
      <div class="flex justify-end gap-2">
        <Button variant="ghost" size="sm" :disabled="busy" @click="mode = 'review'">
          {{ t("common.back") }}
        </Button>
        <Button variant="accent" size="sm" :loading="busy" @click="onReject">
          {{ t("owner.payments.claim.rejectConfirm") }}
        </Button>
      </div>
    </div>
  </section>
</template>
