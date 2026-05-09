<script setup lang="ts">
import { computed } from "vue";
import type { AgreementWithRefs } from "~/services/useAgreements";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";

const props = defineProps<{ row: AgreementWithRefs }>();

const { t } = useI18n();
const { formatRM } = useMoney();

const formatDate = (iso: string) => {
  if (!iso) return "—";
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
};

const today = new Date();
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
