<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import ReportIssueModal from "~/components/tenant/ReportIssueModal.vue";
import type { TicketStatus, TicketPriority, Ticket } from "~/types/ticket";
import type { TicketWithRefs } from "~/services/useTickets";

definePageMeta({ layout: "tenant" });
const { t } = useI18n();
const { tenantId } = useTenantSession();
useHead({ title: () => t("tenant.nav.tickets") });

const rows = ref<TicketWithRefs[]>([]);
const unitId = ref<string | null>(null);
const loading = ref(true);
const showReport = ref(false);

const refresh = async () => {
  if (!tenantId.value) return;
  rows.value = await useTickets().getTicketsForTenant(tenantId.value);
};

onMounted(async () => {
  try {
    const id = tenantId.value;
    if (!id) return;
    const [tk, agreement] = await Promise.all([
      useTickets().getTicketsForTenant(id),
      useAgreements().getActiveAgreementForTenant(id),
    ]);
    rows.value = tk;
    unitId.value = agreement?.unit?.id ?? null;
  } finally {
    loading.value = false;
  }
});

const sorted = computed(() =>
  [...rows.value].sort((a, b) =>
    b.ticket.updatedAt.localeCompare(a.ticket.updatedAt),
  ),
);

const statusTone = (s: TicketStatus) => {
  switch (s) {
    case "new":
      return "pending";
    case "in_progress":
      return "active";
    case "resolved":
      return "paid";
    case "reopened":
      return "overdue";
  }
};

const priorityTone = (p: TicketPriority) =>
  p === "urgent" ? "overdue" : p;

const formatDate = (iso: string) =>
  new Date(iso).toLocaleString("en-MY", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });

const onCreated = (_ticket: Ticket) => {
  refresh();
};
</script>

<template>
  <div>
    <header
      class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-start sm:justify-between"
    >
      <div>
        <h1 class="text-display-sub font-semibold tracking-snug">
          {{ t("tenant.nav.tickets") }}
        </h1>
        <p class="mt-1 text-caption text-ink-muted">
          {{ t("tenant.tickets.subtitle") }}
        </p>
      </div>
      <Button
        variant="primary"
        size="sm"
        class="self-start"
        :disabled="!unitId"
        @click="showReport = true"
      >
        <Icon name="Plus" :size="16" class="mr-1.5" />
        {{ t("tenant.tickets.reportCta") }}
      </Button>
    </header>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <Card v-else-if="rows.length === 0" padding="loose">
      <EmptyState
        icon="Wrench"
        :title="t('tenant.tickets.emptyTitle')"
        :description="t('tenant.tickets.emptyDescription')"
      />
    </Card>

    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <NuxtLink
        v-for="r in sorted"
        :key="r.ticket.id"
        :to="`/tenant/tickets/${r.ticket.id}`"
        class="block rounded-lg border border-line-passive bg-surface-raised p-4 outline-none transition hover:border-line-interactive focus-visible:shadow-focus"
      >
        <div class="flex items-center gap-2">
          <Pill :tone="statusTone(r.ticket.status)">
            {{ t(`tenant.tickets.status.${r.ticket.status}`) }}
          </Pill>
          <Pill :tone="priorityTone(r.ticket.priority)">
            {{ t(`tenant.tickets.priority.${r.ticket.priority}`) }}
          </Pill>
        </div>
        <p class="mt-2 truncate text-body font-medium text-ink">
          {{ r.ticket.title }}
        </p>
        <div class="mt-1 flex items-center gap-2 text-caption text-ink-muted">
          <span>{{ t(`tenant.tickets.category.${r.ticket.category}`) }}</span>
          <span class="text-ink-faint">·</span>
          <span class="tabular-nums">
            {{ t("tenant.tickets.updated", { date: formatDate(r.ticket.updatedAt) }) }}
          </span>
        </div>
        <div
          v-if="r.comments.length > 0"
          class="mt-2 inline-flex items-center gap-1 text-micro text-ink-faint"
        >
          <Icon name="MessageSquare" :size="12" />
          {{ t("tenant.tickets.comments", { n: r.comments.length }) }}
        </div>
      </NuxtLink>
    </div>

    <ReportIssueModal
      v-model:open="showReport"
      :unit-id="unitId"
      :reporter-id="tenantId ?? ''"
      @created="onCreated"
    />
  </div>
</template>
