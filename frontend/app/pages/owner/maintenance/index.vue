<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";
import TicketCard from "~/components/owner/TicketCard.vue";
import TicketCreateModal from "~/components/owner/TicketCreateModal.vue";
import type { Ticket, TicketStatus } from "~/types/ticket";
import type { TicketWithRefs } from "~/services/useTickets";
import type { Unit } from "~/types/unit";
import type { Property } from "~/types/property";
import type { Tenant } from "~/types/tenant";

definePageMeta({ layout: "owner" });

const { t } = useI18n();
useHead({ title: () => t("owner.nav.maintenance") });

const rows = ref<TicketWithRefs[]>([]);
const units = ref<Unit[]>([]);
const properties = ref<Property[]>([]);
const tenants = ref<Tenant[]>([]);
const loading = ref(true);
const showCreate = ref(false);

const load = async () => {
  loading.value = true;
  try {
    const [withRefs, us, props, ts] = await Promise.all([
      useTickets().listWithRefs(),
      useUnits().list(),
      useProperties().list(),
      useTenants().list(),
    ]);
    // Newest activity first within each column.
    rows.value = withRefs.sort((a, b) =>
      b.ticket.updatedAt.localeCompare(a.ticket.updatedAt),
    );
    units.value = us;
    properties.value = props;
    tenants.value = ts;
  } finally {
    loading.value = false;
  }
};

onMounted(load);

const COLUMNS: { status: TicketStatus; tone: string }[] = [
  { status: "new", tone: "pending" },
  { status: "in_progress", tone: "active" },
  { status: "resolved", tone: "paid" },
  { status: "reopened", tone: "overdue" },
];

const byStatus = computed<Record<TicketStatus, TicketWithRefs[]>>(() => {
  const map: Record<TicketStatus, TicketWithRefs[]> = {
    new: [],
    in_progress: [],
    resolved: [],
    reopened: [],
  };
  rows.value.forEach((r) => {
    map[r.ticket.status].push(r);
  });
  return map;
});

const isEmpty = computed(() => rows.value.length === 0);

const onCreated = (ticket: Ticket) => {
  // Re-fetch to pick up the hydrated refs (unit/property/reporter) cleanly.
  load();
};
</script>

<template>
  <div>
    <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-display-sub font-semibold tracking-snug">
          {{ t("owner.nav.maintenance") }}
        </h1>
        <p class="mt-2 text-caption text-ink-muted">
          {{ t("owner.tickets.subtitle") }}
        </p>
      </div>
      <Button variant="primary" @click="showCreate = true">
        <Icon name="Plus" :size="14" class="mr-1.5" />
        {{ t("owner.tickets.createCta") }}
      </Button>
    </header>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <Card v-else-if="isEmpty" padding="loose">
      <EmptyState
        icon="Wrench"
        :title="t('owner.tickets.empty.title')"
        :description="t('owner.tickets.empty.description')"
      >
        <template #action>
          <Button variant="primary" @click="showCreate = true">
            {{ t("owner.tickets.createCta") }}
          </Button>
        </template>
      </EmptyState>
    </Card>

    <div
      v-else
      class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
    >
      <section
        v-for="col in COLUMNS"
        :key="col.status"
        class="flex flex-col gap-3 rounded-lg border border-line-passive bg-surface-page p-3"
      >
        <header class="flex items-center justify-between gap-2 px-1">
          <h2 class="text-caption font-semibold uppercase tracking-wide text-ink-muted">
            {{ t(`owner.tickets.status.${col.status}`) }}
          </h2>
          <span class="text-caption tabular-nums text-ink-faint">
            {{ byStatus[col.status].length }}
          </span>
        </header>

        <div class="flex flex-col gap-2">
          <TicketCard
            v-for="row in byStatus[col.status]"
            :key="row.ticket.id"
            :row="row"
          />
          <p
            v-if="byStatus[col.status].length === 0"
            class="px-1 py-4 text-center text-caption text-ink-faint"
          >
            {{ t("owner.tickets.columnEmpty") }}
          </p>
        </div>
      </section>
    </div>

    <TicketCreateModal
      v-model:open="showCreate"
      :units="units"
      :properties="properties"
      :tenants="tenants"
      @created="onCreated"
    />
  </div>
</template>
