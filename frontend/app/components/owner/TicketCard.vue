<script setup lang="ts">
import type { TicketPriority } from "~/types/ticket";
import type { TicketWithRefs } from "~/services/useTickets";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";

defineProps<{ row: TicketWithRefs }>();
const { t } = useI18n();

const priorityTone = (p: TicketPriority) => {
  switch (p) {
    case "low":
      return "low";
    case "medium":
      return "medium";
    case "high":
      return "high";
    case "urgent":
      return "overdue"; // shares the red attention tone with overdue invoices
  }
};

const formatRelative = (iso: string) => {
  const ms = Date.now() - new Date(iso).getTime();
  const hours = Math.round(ms / (1000 * 60 * 60));
  if (hours < 1) return "just now";
  if (hours < 24) return `${hours}h ago`;
  const days = Math.round(hours / 24);
  return `${days}d ago`;
};
</script>

<template>
  <NuxtLink
    :to="`/owner/maintenance/${row.ticket.id}`"
    class="block rounded-lg outline-none transition hover:opacity-95 focus-visible:shadow-focus"
  >
    <Card padding="standard">
      <div class="mb-2 flex items-center justify-between gap-2">
        <Pill :tone="priorityTone(row.ticket.priority)">
          {{ t(`owner.tickets.priority.${row.ticket.priority}`) }}
        </Pill>
        <span class="text-micro tabular-nums text-ink-faint">
          {{ formatRelative(row.ticket.updatedAt) }}
        </span>
      </div>

      <h3 class="line-clamp-2 text-body font-medium text-ink">
        {{ row.ticket.title }}
      </h3>

      <p class="mt-2 truncate text-caption text-ink-muted">
        <Icon name="MapPin" :size="12" class="mr-1 inline" />
        {{ row.property?.name ?? "—" }} · {{ row.unit?.label ?? "—" }}
      </p>

      <div
        class="mt-3 flex items-center justify-between gap-2 text-micro text-ink-faint"
      >
        <span class="inline-flex items-center gap-1">
          <Icon name="Tag" :size="12" />
          {{ t(`owner.tickets.category.${row.ticket.category}`) }}
        </span>
        <span class="inline-flex items-center gap-3">
          <span
            v-if="row.comments.length > 0"
            class="inline-flex items-center gap-1"
          >
            <Icon name="MessageSquare" :size="12" />
            {{ row.comments.length }}
          </span>
          <span class="inline-flex items-center gap-1">
            <Icon
              :name="row.ticket.reporterRole === 'tenant' ? 'User' : 'Building2'"
              :size="12"
            />
            {{ row.reporter?.name ?? t("owner.tickets.byOwner") }}
          </span>
        </span>
      </div>
    </Card>
  </NuxtLink>
</template>
