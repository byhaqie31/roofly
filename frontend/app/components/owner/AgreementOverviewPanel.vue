<script setup lang="ts">
import { computed, ref } from "vue";
import type { AgreementWithRefs } from "~/services/useAgreements";
import type { Agreement } from "~/types/agreement";
import { bankLabel } from "~/config/banks";
import { maskAccountNumber } from "~/utils/paymentClaim";
import { useToast } from "~/composables/useToast";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import AgreementPayoutSelect from "~/components/owner/AgreementPayoutSelect.vue";

const props = defineProps<{ row: AgreementWithRefs }>();
const emit = defineEmits<{ updated: [agreement: Agreement] }>();

const { t } = useI18n();
const { formatRM } = useMoney();
const { show } = useToast();

// Payout account (spec 2026-10-08 § 3.2) — not a term, so it's changed here,
// at any status, without sending a sent/accepted agreement back to draft.
const editingPayout = ref(false);
const payoutDraft = ref<string | null>(null);
const savingPayout = ref(false);

const startPayoutEdit = () => {
  payoutDraft.value = props.row.agreement.payoutAccountId ?? null;
  editingPayout.value = true;
};

const savePayout = async () => {
  savingPayout.value = true;
  try {
    const updated = await useAgreements().update(props.row.agreement.id, { payoutAccountId: payoutDraft.value });
    show(t("common.savedToast"), "success");
    editingPayout.value = false;
    emit("updated", updated);
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    savingPayout.value = false;
  }
};

const formatDate = (iso: string) => {
  if (!iso) return "—";
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
};

const today = new Date();

const formatStamp = (iso: string | null | undefined) => (iso ? formatDate(iso.slice(0, 10)) : "");

/** Banner copy for the review flow; null for statuses with nothing to say (active/expired/terminated). */
const review = computed(() => {
  const a = props.row.agreement;
  switch (a.status) {
    case "pending_review":
      return { tone: "warn", icon: "Send" as const, text: t("owner.agreements.review.sentOn", { date: formatStamp(a.sentAt) }), note: null };
    case "accepted":
      return { tone: "good", icon: "CircleCheck" as const, text: t("owner.agreements.review.acceptedOn", { date: formatStamp(a.acceptedAt) }), note: null };
    case "draft":
      return a.reviewNote
        ? { tone: "warn", icon: "MessageSquare" as const, text: t("owner.agreements.review.changesRequestedOn", { date: formatStamp(a.changesRequestedAt) }), note: a.reviewNote }
        : { tone: "muted", icon: "EyeOff" as const, text: t("owner.agreements.review.draftHint"), note: null };
    default:
      return null;
  }
});
today.setHours(0, 0, 0, 0);
const startMs = computed(() => new Date(props.row.agreement.startDate).getTime());
const endMs = computed(() => new Date(props.row.agreement.endDate).getTime());
const dayMs = 24 * 60 * 60 * 1000;

const termDays = computed(() =>
  Math.max(0, Math.round((endMs.value - startMs.value) / dayMs)),
);

const termSummary = computed(() => {
  const months = Math.round(termDays.value / 30);
  if (months >= 12 && months % 12 === 0) {
    const years = months / 12;
    return t("owner.agreements.detail.overview.years", { n: years });
  }
  return t("owner.agreements.detail.overview.months", { n: months });
});

interface TermStatus {
  label: string;
  tone: "active" | "expired" | "draft" | "neutral";
}

const termStatus = computed<TermStatus>(() => {
  const now = today.getTime();
  if (startMs.value > now) {
    const days = Math.round((startMs.value - now) / dayMs);
    return {
      label: t("owner.agreements.detail.overview.startsIn", { n: days }),
      tone: "draft",
    };
  }
  if (endMs.value < now) {
    const days = Math.round((now - endMs.value) / dayMs);
    return {
      label: t("owner.agreements.detail.overview.expiredAgo", { n: days }),
      tone: "expired",
    };
  }
  const days = Math.round((endMs.value - now) / dayMs);
  if (days <= 60) {
    return {
      label: t("owner.agreements.detail.overview.expiringIn", { n: days }),
      tone: "expired",
    };
  }
  return {
    label: t("owner.agreements.detail.overview.daysRemaining", { n: days }),
    tone: "active",
  };
});

const tilesPillToneClass = (tone: TermStatus["tone"]) => {
  switch (tone) {
    case "active":
      return "text-status-active";
    case "expired":
      return "text-status-expired";
    case "draft":
      return "text-status-draft";
    default:
      return "text-ink";
  }
};
</script>

<template>
  <div class="space-y-6">
    <p class="text-caption text-ink-muted">
      {{ t("owner.agreements.detail.overviewHelp") }}
    </p>

    <!-- Review state (spec 2026-10-07 agreement-review): where the tenant's answer stands -->
    <div
      v-if="review"
      class="flex items-start gap-3 rounded-md border px-4 py-3"
      :class="review.tone === 'warn'
        ? 'border-status-pending bg-status-pending-soft'
        : review.tone === 'good'
          ? 'border-status-paid bg-status-paid-soft'
          : 'border-line-passive bg-surface-page'"
      role="status"
    >
      <Icon :name="review.icon" :size="16" class="mt-0.5 shrink-0" />
      <div class="min-w-0">
        <p class="text-body font-medium text-ink">{{ review.text }}</p>
        <p v-if="review.note" class="mt-1 text-caption text-ink-muted">
          <span class="font-medium text-ink">{{ t("owner.agreements.review.noteLabel") }}:</span>
          “{{ review.note }}”
        </p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
      <div class="rounded-md border border-line-passive bg-surface-page p-4">
        <div class="text-caption text-ink-muted">
          {{ t("owner.agreements.detail.overview.status") }}
        </div>
        <div class="mt-2">
          <Pill :tone="row.agreement.status">
            {{ t(`owner.agreements.status.${row.agreement.status}`) }}
          </Pill>
        </div>
      </div>
      <div class="rounded-md border border-line-passive bg-surface-page p-4">
        <div class="text-caption text-ink-muted">
          {{ t("owner.agreements.detail.overview.term") }}
        </div>
        <div class="mt-1 text-card-title font-semibold text-ink tabular-nums">
          {{ termSummary }}
        </div>
      </div>
      <div class="rounded-md border border-line-passive bg-surface-page p-4">
        <div class="text-caption text-ink-muted">
          {{ t("owner.agreements.detail.overview.monthlyRent") }}
        </div>
        <div class="mt-1 text-card-title font-semibold text-ink tabular-nums">
          {{ formatRM(row.agreement.rentAmount) }}
        </div>
      </div>
      <div class="rounded-md border border-line-passive bg-surface-page p-4">
        <div class="text-caption text-ink-muted">
          {{ t("owner.agreements.detail.overview.timeline") }}
        </div>
        <div
          :class="[
            'mt-1 text-body font-semibold tabular-nums',
            tilesPillToneClass(termStatus.tone),
          ]"
        >
          {{ termStatus.label }}
        </div>
      </div>
    </div>

    <section class="space-y-3">
      <h3
        class="text-caption font-semibold uppercase tracking-wide text-ink-muted"
      >
        {{ t("owner.agreements.detail.overview.parties") }}
      </h3>
      <ul class="divide-y divide-line-passive">
        <li class="flex items-center justify-between gap-3 py-3">
          <div class="min-w-0">
            <div class="text-caption text-ink-muted">
              {{ t("owner.agreements.fields.tenant") }}
            </div>
            <div class="mt-0.5 truncate text-body text-ink">
              <NuxtLink
                v-if="row.tenant"
                :to="`/owner/tenants/${row.tenant.id}`"
                class="inline-flex items-center gap-1 underline-offset-2 hover:underline"
              >
                <Icon name="User" :size="14" />
                {{ row.tenant.name }}
              </NuxtLink>
              <span v-else class="text-ink-muted">
                {{ t("owner.agreements.unknownTenant") }}
              </span>
            </div>
          </div>
        </li>
        <li class="flex items-center justify-between gap-3 py-3">
          <div class="min-w-0">
            <div class="text-caption text-ink-muted">
              {{ t("owner.agreements.fields.property") }}
            </div>
            <div class="mt-0.5 truncate text-body text-ink">
              <NuxtLink
                v-if="row.property"
                :to="`/owner/properties/${row.property.id}`"
                class="inline-flex items-center gap-1 underline-offset-2 hover:underline"
              >
                <Icon name="Building2" :size="14" />
                {{ row.property.name }}
              </NuxtLink>
              <span v-else class="text-ink-muted">—</span>
            </div>
          </div>
        </li>
        <li class="flex items-center justify-between gap-3 py-3">
          <div class="min-w-0">
            <div class="text-caption text-ink-muted">
              {{ t("owner.agreements.fields.unit") }}
            </div>
            <div class="mt-0.5 truncate text-body text-ink">
              <span class="inline-flex items-center gap-1">
                <Icon name="Home" :size="14" class="text-ink-muted" />
                {{ row.unit?.label ?? "—" }}
              </span>
            </div>
          </div>
        </li>
      </ul>
    </section>

    <section class="space-y-3">
      <h3
        class="text-caption font-semibold uppercase tracking-wide text-ink-muted"
      >
        {{ t("owner.agreements.detail.overview.term") }}
      </h3>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div class="rounded-md border border-line-passive bg-surface-page p-4">
          <div class="text-caption text-ink-muted">
            {{ t("owner.agreements.fields.startDate") }}
          </div>
          <div class="mt-1 text-body font-medium text-ink tabular-nums">
            {{ formatDate(row.agreement.startDate) }}
          </div>
        </div>
        <div class="rounded-md border border-line-passive bg-surface-page p-4">
          <div class="text-caption text-ink-muted">
            {{ t("owner.agreements.fields.endDate") }}
          </div>
          <div class="mt-1 text-body font-medium text-ink tabular-nums">
            {{ formatDate(row.agreement.endDate) }}
          </div>
        </div>
      </div>
    </section>

    <section class="space-y-3">
      <h3 class="text-caption font-semibold uppercase tracking-wide text-ink-muted">
        {{ t("owner.agreements.payout.title") }}
      </h3>
      <div class="rounded-md border border-line-passive bg-surface-page p-4">
        <div v-if="editingPayout" class="space-y-3">
          <div class="sm:max-w-md">
            <AgreementPayoutSelect v-model="payoutDraft" />
          </div>
          <div class="flex gap-2">
            <Button variant="primary" size="sm" :loading="savingPayout" @click="savePayout">
              {{ t("common.save") }}
            </Button>
            <Button variant="ghost" size="sm" :disabled="savingPayout" @click="editingPayout = false">
              {{ t("common.cancel") }}
            </Button>
          </div>
        </div>
        <div v-else class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div v-if="row.payoutAccount" class="min-w-0">
            <div class="text-body font-medium text-ink">
              {{ row.payoutAccount.label }}
              <span v-if="!row.agreement.payoutAccountId" class="text-caption font-normal text-ink-muted">
                · {{ t("owner.agreements.payout.defaultHint") }}
              </span>
            </div>
            <div class="mt-0.5 text-caption text-ink-muted tabular-nums">
              {{ [bankLabel(row.payoutAccount.bank), maskAccountNumber(row.payoutAccount.accountNumber)].filter(Boolean).join(" · ") }}
            </div>
          </div>
          <p v-else class="text-caption text-ink-muted">
            {{ t("owner.agreements.payout.none") }}
            <NuxtLink to="/owner/settings?tab=payouts" class="text-ink underline underline-offset-2">
              {{ t("owner.agreements.payout.addLink") }}
            </NuxtLink>
          </p>
          <Button v-if="row.payoutAccount" variant="ghost" size="sm" class="self-start" @click="startPayoutEdit">
            {{ t("owner.agreements.payout.change") }}
          </Button>
        </div>
      </div>
    </section>

    <section class="space-y-3">
      <h3
        class="text-caption font-semibold uppercase tracking-wide text-ink-muted"
      >
        {{ t("owner.agreements.detail.overview.money") }}
      </h3>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
        <div class="rounded-md border border-line-passive bg-surface-page p-4">
          <div class="text-caption text-ink-muted">
            {{ t("owner.agreements.fields.rentAmount") }}
          </div>
          <div class="mt-1 text-body font-medium text-ink tabular-nums">
            {{ formatRM(row.agreement.rentAmount) }}
          </div>
        </div>
        <div class="rounded-md border border-line-passive bg-surface-page p-4">
          <div class="text-caption text-ink-muted">
            {{ t("owner.agreements.fields.depositAmount") }}
          </div>
          <div class="mt-1 text-body font-medium text-ink tabular-nums">
            {{ formatRM(row.agreement.depositAmount) }}
          </div>
        </div>
        <div class="rounded-md border border-line-passive bg-surface-page p-4">
          <div class="text-caption text-ink-muted">
            {{ t("owner.agreements.fields.lateFee") }}
          </div>
          <div class="mt-1 text-body font-medium text-ink tabular-nums">
            {{ formatRM(row.agreement.lateFee) }}
          </div>
        </div>
        <div class="rounded-md border border-line-passive bg-surface-page p-4">
          <div class="text-caption text-ink-muted">
            {{ t("owner.agreements.fields.rentDueDay") }}
          </div>
          <div class="mt-1 text-body font-medium text-ink tabular-nums">
            {{ t("owner.agreements.dueOn", { day: row.agreement.rentDueDay }) }}
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
