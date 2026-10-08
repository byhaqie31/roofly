<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import type { InvoiceStatus } from "~/types/invoice";
import type { InvoiceWithRefs } from "~/services/useInvoices";
import { transferClaimFormSchema, type TransferClaimFormDto } from "~/schemas/payoutAccount";
import { latestRejectedClaim, pendingClaim, todayIso } from "~/utils/paymentClaim";
import { useToast } from "~/composables/useToast";
import Modal from "~/components/ui/Modal.vue";
import Pill from "~/components/ui/Pill.vue";
import Button from "~/components/ui/Button.vue";
import Select from "~/components/ui/Select.vue";
import Input from "~/components/ui/Input.vue";
import Icon from "~/components/ui/Icon.vue";
import PayoutDetails from "~/components/tenant/PayoutDetails.vue";

/**
 * Tenant pays an invoice (spec 2026-10-08). Two methods:
 * - DuitNow / bank transfer — live: pay in your banking app, then "I've paid"
 *   with the reference; the landlord confirms.
 * - Online banking / card — Coming soon behind `features.onlinePayments`;
 *   with the flag on it runs the simulated FPX round-trip the gateway replaces.
 * Doubles as the receipt for settled invoices.
 */
const props = defineProps<{
  open: boolean;
  row: InvoiceWithRefs | null;
}>();

const emit = defineEmits<{
  "update:open": [value: boolean];
  paid: [];
}>();

const { t } = useI18n();
const { show } = useToast();
const { formatRM } = useMoney();
const { toFieldErrors, toErrorCode } = useApiError();
const onlineEnabled = useEnv().features.onlinePayments;
const { public: { features } } = useRuntimeConfig();

type Method = "transfer" | "online";
const method = ref<Method>("transfer");
const submitting = ref(false);
const bank = ref("maybank");

// FPX participating banks — proper nouns, not translated. Gateway path only.
const bankOptions = [
  { value: "maybank", label: "Maybank2u" },
  { value: "cimb", label: "CIMB Clicks" },
  { value: "public", label: "Public Bank" },
  { value: "rhb", label: "RHB Now" },
  { value: "hongleong", label: "Hong Leong Connect" },
  { value: "bankislam", label: "Bank Islam" },
  { value: "ambank", label: "AmOnline" },
];

const statusToneMap = {
  pending: "pending",
  paid: "paid",
  overdue: "overdue",
  cancelled: "cancelled",
} as const satisfies Record<InvoiceStatus, string>;

const formatDate = (iso: string) => {
  if (!iso) return "—";
  const [y, m, d] = iso.slice(0, 10).split("-");
  return `${d}/${m}/${y}`;
};

const total = computed(() => {
  const inv = props.row?.invoice;
  return inv ? inv.amount + inv.lateFee : 0;
});

const periodLabel = computed(() => {
  const due = props.row?.invoice.dueDate;
  if (!due) return "";
  return new Date(due).toLocaleString("en-MY", { month: "long", year: "numeric" });
});

const isPayable = computed(
  () => props.row?.invoice.status === "pending" || props.row?.invoice.status === "overdue",
);
const claim = computed(() => (props.row ? pendingClaim(props.row.payments) : null));
const rejected = computed(() => (props.row ? latestRejectedClaim(props.row.payments) : null));
const account = computed(() => props.row?.payoutAccount ?? null);
const receipts = computed(() => props.row?.payments.filter((p) => p.status === "successful") ?? []);

const { defineField, handleSubmit, errors, resetForm, setErrors } = useForm<TransferClaimFormDto>({
  validationSchema: toTypedSchema(transferClaimFormSchema),
  initialValues: { reference: "", paidAt: todayIso(), note: "" },
});
const [reference] = defineField("reference");
const [paidAt] = defineField("paidAt");
const [note] = defineField("note");

const onClaim = handleSubmit(async (values) => {
  if (!props.row) return;
  submitting.value = true;
  try {
    await useInvoices().claimTransferForTenant(props.row.invoice.id, {
      reference: values.reference.trim(),
      paidAt: values.paidAt,
      note: values.note?.trim() || undefined,
    });
    show(t("tenant.payments.payModal.claimedToast"), "success");
    emit("paid");
    emit("update:open", false);
  } catch (err) {
    const fieldErrors = toFieldErrors(err);
    if (fieldErrors) {
      setErrors(fieldErrors);
      return;
    }
    const code = toErrorCode(err);
    show(
      code === "claim_pending"
        ? t("tenant.payments.payModal.errors.claimPending")
        : code === "no_payout_account"
          ? t("tenant.payments.payModal.errors.noPayoutAccount")
          : t("common.genericError"),
      "danger",
    );
  } finally {
    submitting.value = false;
  }
});

const onPayOnline = async () => {
  if (!props.row) return;
  submitting.value = true;
  try {
    // Simulated until the gateway lands; every FPX bank maps to method "fpx".
    await useInvoices().payForTenant(props.row.invoice.id, "fpx");
    show(t("tenant.payments.payModal.successToast"), "success");
    emit("paid");
    emit("update:open", false);
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    submitting.value = false;
  }
};

const canClaim = computed(() => isPayable.value && !claim.value && method.value === "transfer" && account.value !== null);
const canPayOnline = computed(() => isPayable.value && !claim.value && method.value === "online" && onlineEnabled);

watch(
  () => props.open,
  (isOpen) => {
    if (!isOpen) return;
    method.value = "transfer";
    bank.value = "maybank";
    resetForm({ values: { reference: "", paidAt: todayIso(), note: "" } });
  },
);

const methodCardClass = (active: boolean, disabled = false) => [
  "flex w-full items-start gap-3 rounded-md border p-3 text-left outline-none transition focus-visible:shadow-focus",
  disabled
    ? "cursor-not-allowed border-line-passive opacity-60"
    : active
      ? "border-line-interactive bg-surface-page"
      : "border-line-passive hover:border-line-interactive",
];
</script>

<template>
  <Modal
    :open="open"
    :title="row ? t('tenant.payments.payModal.title') : ''"
    :description="row ? row.invoice.invoiceNumber : undefined"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <div v-if="row" class="space-y-5">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="text-caption text-ink-muted">
            {{ t("tenant.payments.payModal.period") }}
          </div>
          <div class="text-body text-ink">{{ periodLabel }}</div>
          <div class="mt-1 text-caption text-ink-muted tabular-nums">
            {{ t("tenant.payments.dueOn", { date: formatDate(row.invoice.dueDate) }) }}
          </div>
        </div>
        <Pill v-if="claim" tone="pending">{{ t("tenant.payments.status.awaiting") }}</Pill>
        <Pill v-else :tone="statusToneMap[row.invoice.status]">
          {{ t(`tenant.payments.status.${row.invoice.status}`) }}
        </Pill>
      </div>

      <section class="rounded-md border border-line-passive bg-surface-page p-4">
        <dl class="space-y-2 text-body">
          <div class="flex items-baseline justify-between">
            <dt class="text-ink-muted">{{ t("tenant.payments.payModal.rent") }}</dt>
            <dd class="tabular-nums text-ink">{{ formatRM(row.invoice.amount) }}</dd>
          </div>
          <div v-if="row.invoice.lateFee > 0" class="flex items-baseline justify-between">
            <dt class="text-status-overdue">{{ t("tenant.payments.payModal.lateFee") }}</dt>
            <dd class="tabular-nums text-status-overdue">{{ formatRM(row.invoice.lateFee) }}</dd>
          </div>
          <div class="flex items-baseline justify-between border-t border-line-passive pt-2">
            <dt class="font-semibold text-ink">{{ t("tenant.payments.payModal.total") }}</dt>
            <dd class="text-card-title font-semibold tabular-nums text-ink">{{ formatRM(total) }}</dd>
          </div>
        </dl>
      </section>

      <!-- Claimed: waiting on the landlord -->
      <section
        v-if="isPayable && claim"
        class="flex items-start gap-3 rounded-md border border-status-pending bg-status-pending-soft px-4 py-3"
        role="status"
      >
        <Icon name="Hourglass" :size="16" class="mt-0.5 shrink-0" />
        <div class="min-w-0 text-caption">
          <p class="text-body font-medium text-ink">{{ t("tenant.payments.payModal.awaitingTitle") }}</p>
          <p class="mt-1 text-ink-muted">
            {{ t("tenant.payments.payModal.awaitingHelp", { date: formatDate(claim.paidAt), reference: claim.reference ?? "—" }) }}
          </p>
        </div>
      </section>

      <!-- Payable: pick a method -->
      <template v-else-if="isPayable">
        <div
          v-if="rejected"
          class="flex items-start gap-3 rounded-md border border-status-overdue bg-status-overdue-soft px-4 py-3"
          role="alert"
        >
          <Icon name="CircleAlert" :size="16" class="mt-0.5 shrink-0 text-status-overdue" />
          <div class="min-w-0 text-caption">
            <p class="text-body font-medium text-ink">{{ t("tenant.payments.payModal.rejectedTitle") }}</p>
            <p class="mt-1 text-ink-muted">“{{ rejected.rejectionReason }}”</p>
          </div>
        </div>

        <section class="space-y-2" role="radiogroup" :aria-label="t('tenant.payments.payModal.methodLabel')">
          <div class="text-caption font-semibold uppercase tracking-wide text-ink-muted">
            {{ t("tenant.payments.payModal.methodLabel") }}
          </div>
          <button
            type="button"
            role="radio"
            :aria-checked="method === 'transfer'"
            :class="methodCardClass(method === 'transfer')"
            @click="method = 'transfer'"
          >
            <Icon name="ArrowLeftRight" :size="18" class="mt-0.5 shrink-0 text-ink-muted" />
            <span class="min-w-0">
              <span class="block text-body font-medium text-ink">{{ t("tenant.payments.payModal.transfer.title") }}</span>
              <span class="block text-caption text-ink-muted">{{ t("tenant.payments.payModal.transfer.help") }}</span>
            </span>
          </button>
          <button
            type="button"
            role="radio"
            :aria-checked="method === 'online'"
            :aria-disabled="!onlineEnabled"
            :disabled="!onlineEnabled"
            :class="methodCardClass(method === 'online', !onlineEnabled)"
            @click="method = 'online'"
          >
            <Icon name="CreditCard" :size="18" class="mt-0.5 shrink-0 text-ink-muted" />
            <span class="min-w-0 flex-1">
              <span class="flex flex-wrap items-center gap-2">
                <span class="text-body font-medium text-ink">{{ t("tenant.payments.payModal.online.title") }}</span>
                <Pill v-if="!onlineEnabled" tone="neutral">{{ t("tenant.payments.payModal.online.comingSoon") }}</Pill>
              </span>
              <span class="block text-caption text-ink-muted">{{ t("tenant.payments.payModal.online.help") }}</span>
            </span>
          </button>
        </section>

        <!-- DuitNow / bank transfer -->
        <template v-if="method === 'transfer'">
          <section v-if="account" class="space-y-2">
            <div class="text-caption font-semibold uppercase tracking-wide text-ink-muted">
              {{ t("tenant.payTo.title") }}
            </div>
            <PayoutDetails :account="account" :reference="row.invoice.invoiceNumber" />
            <p class="text-micro text-ink-faint">{{ t("tenant.payments.payModal.transfer.steps") }}</p>
          </section>
          <p v-else class="rounded-md border border-line-passive bg-surface-page px-4 py-3 text-caption text-ink-muted">
            {{ t("tenant.payTo.none") }}
          </p>

          <form v-if="account" id="claim-form" class="space-y-4 border-t border-line-passive pt-4" @submit.prevent="onClaim">
            <div class="text-body font-medium text-ink">{{ t("tenant.payments.payModal.claim.title") }}</div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input
                v-model="reference"
                :label="t('tenant.payments.payModal.claim.reference')"
                :placeholder="t('tenant.payments.payModal.claim.referencePlaceholder')"
                :error="errors.reference"
              />
              <Input
                v-model="paidAt"
                type="date"
                :max="todayIso()"
                :label="t('tenant.payments.payModal.claim.paidAt')"
                :error="errors.paidAt"
              />
            </div>
            <label class="block">
              <span class="mb-1.5 block text-caption text-ink-strong">{{ t("tenant.payments.payModal.claim.note") }}</span>
              <textarea
                v-model="note"
                rows="2"
                maxlength="500"
                :placeholder="t('tenant.payments.payModal.claim.notePlaceholder')"
                class="w-full rounded-sm border border-line-passive bg-surface-page px-3 py-2 text-body text-ink outline-none transition focus:border-line-interactive focus:shadow-focus"
              />
              <span v-if="errors.note" class="mt-1.5 block text-caption text-accent" role="alert">{{ errors.note }}</span>
            </label>
            <p v-if="features.documents" class="flex items-center gap-2 text-micro text-ink-faint">
              <Icon name="Paperclip" :size="14" />
              {{ t("tenant.payments.payModal.claim.receiptPlaceholder") }}
            </p>
          </form>
        </template>

        <!-- Online (gateway) — only reachable with the flag on -->
        <section v-else class="space-y-3">
          <Select v-model="bank" :options="bankOptions" :label="t('tenant.payments.payModal.bank')" />
          <p class="flex items-center gap-1.5 text-micro text-ink-faint">
            <Icon name="Info" :size="12" class="shrink-0" />
            {{ t("tenant.payments.payModal.simNote") }}
          </p>
        </section>
      </template>

      <!-- Settled: the receipt -->
      <section v-else-if="receipts.length > 0">
        <div class="text-caption font-semibold uppercase tracking-wide text-ink-muted">
          {{ t("tenant.payments.payModal.receipt") }}
        </div>
        <ul class="mt-2 divide-y divide-line-passive">
          <li v-for="p in receipts" :key="p.id" class="flex items-baseline justify-between py-2 text-caption">
            <div>
              <span class="text-ink">{{ t(`tenant.payments.methods.${p.method}`) }}</span>
              <span class="ml-2 text-ink-muted tabular-nums">{{ formatDate(p.paidAt) }}</span>
              <span v-if="p.reference" class="ml-2 text-ink-faint">· {{ p.reference }}</span>
            </div>
            <span class="tabular-nums text-ink">{{ formatRM(p.amount) }}</span>
          </li>
        </ul>
      </section>
    </div>

    <template #footer>
      <Button variant="ghost" :disabled="submitting" @click="emit('update:open', false)">
        {{ t("common.close") }}
      </Button>
      <Button v-if="canClaim" type="submit" form="claim-form" variant="primary" :loading="submitting">
        <Icon name="Check" :size="14" class="mr-1" />
        {{ t("tenant.payments.payModal.claim.submit") }}
      </Button>
      <Button v-else-if="canPayOnline" variant="primary" :loading="submitting" @click="onPayOnline">
        <Icon v-if="!submitting" name="CreditCard" :size="14" class="mr-1" />
        {{
          submitting
            ? t("tenant.payments.payModal.processing")
            : t("tenant.payments.payModal.payCta", { amount: formatRM(total) })
        }}
      </Button>
    </template>
  </Modal>
</template>
