<script setup lang="ts">
import type { AgreementStatus } from "~/types/agreement";
import type { AgreementWithRefs } from "~/services/useAgreements";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";

defineProps<{ row: AgreementWithRefs }>();
const { t } = useI18n();
const { formatRM } = useMoney();

const statusTone = (status: AgreementStatus) => {
  switch (status) {
    case "draft":
      return "draft";
    case "active":
      return "active";
    case "expired":
      return "expired";
    case "terminated":
      return "terminated";
  }
};

const formatDate = (iso: string) => {
  if (!iso) return "—";
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
};
</script>

<template>
  <NuxtLink
    :to="`/owner/agreements/${row.agreement.id}`"
    class="block w-full rounded-lg text-left outline-none transition hover:opacity-95 focus-visible:shadow-focus"
  >
    <Card padding="standard">
      <div class="mb-3 flex items-start justify-between gap-3">
        <Pill :tone="statusTone(row.agreement.status)">
          {{ t(`owner.agreements.status.${row.agreement.status}`) }}
        </Pill>
        <span class="text-micro tabular-nums text-ink-faint">
          {{ formatDate(row.agreement.startDate) }} →
          {{ formatDate(row.agreement.endDate) }}
        </span>
      </div>

      <h3 class="truncate text-card-title text-ink">
        {{ row.tenant?.name ?? t("owner.agreements.unknownTenant") }}
      </h3>

      <p class="mt-1 truncate text-caption text-ink-muted">
        <Icon name="MapPin" :size="12" class="mr-1 inline" />
        {{ row.property?.name ?? "—" }} · {{ row.unit?.label ?? "—" }}
      </p>

      <div
        class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-ink-muted"
      >
        <span class="inline-flex items-center gap-1 tabular-nums">
          <Icon name="Wallet" :size="14" />
          {{ formatRM(row.agreement.rentAmount) }}
          <span class="text-ink-faint">/mo</span>
        </span>
        <span class="inline-flex items-center gap-1 tabular-nums">
          <Icon name="Calendar" :size="14" />
          {{ t("owner.agreements.dueOn", { day: row.agreement.rentDueDay }) }}
        </span>
      </div>
    </Card>
  </NuxtLink>
</template>
