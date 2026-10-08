<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import PayInvoiceModal from "~/components/tenant/PayInvoiceModal.vue";
import type { InvoiceStatus } from "~/types/invoice";
import type { InvoiceWithRefs } from "~/services/useInvoices";
import { latestRejectedClaim, pendingClaim } from "~/utils/paymentClaim";

definePageMeta({ layout: "tenant" });
const { t } = useI18n();
const { formatRM } = useMoney();
const { tenantId } = useTenantSession();
useHead({ title: () => t("tenant.nav.payments") });

const rows = ref<InvoiceWithRefs[]>([]);
const loading = ref(true);
const selected = ref<InvoiceWithRefs | null>(null);
const showModal = ref(false);

const refresh = async () => {
  if (tenantId.value) {
    rows.value = await useInvoices().getInvoicesForTenant(tenantId.value);
  }
};

onMounted(async () => {
  try {
    await refresh();
  } finally {
    loading.value = false;
  }
});

const sorted = computed(() =>
  [...rows.value].sort((a, b) =>
    b.invoice.dueDate.localeCompare(a.invoice.dueDate),
  ),
);

const outstanding = computed(() =>
  rows.value
    .filter(
      (r) => r.invoice.status === "pending" || r.invoice.status === "overdue",
    )
    .reduce((sum, r) => sum + r.invoice.amount + r.invoice.lateFee, 0),
);

const statusToneMap = {
  pending: "pending",
  paid: "paid",
  overdue: "overdue",
  cancelled: "cancelled",
} as const satisfies Record<InvoiceStatus, string>;

const isUnpaid = (r: InvoiceWithRefs) =>
  r.invoice.status === "pending" || r.invoice.status === "overdue";
// "I've paid" sent, landlord hasn't confirmed yet (spec 2026-10-08).
const isAwaiting = (r: InvoiceWithRefs) => isUnpaid(r) && pendingClaim(r.payments) !== null;
const wasRejected = (r: InvoiceWithRefs) => isUnpaid(r) && latestRejectedClaim(r.payments) !== null;

const formatDate = (iso: string) => {
  if (!iso) return "—";
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
};

const periodLabel = (iso: string) =>
  new Date(iso).toLocaleString("en-MY", { month: "short", year: "numeric" });

const open = (r: InvoiceWithRefs) => {
  selected.value = r;
  showModal.value = true;
};
</script>

<template>
  <div>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("tenant.nav.payments") }}
      </h1>
      <p class="mt-1 text-caption text-ink-muted">
        {{ t("tenant.payments.subtitle") }}
      </p>
    </header>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <Card v-else-if="rows.length === 0" padding="loose">
      <EmptyState
        icon="Receipt"
        :title="t('tenant.payments.emptyTitle')"
        :description="t('tenant.payments.emptyDescription')"
      />
    </Card>

    <template v-else>
      <!-- Outstanding summary -->
      <Card padding="standard" class="mb-4 sm:mb-6">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-caption text-ink-muted">
              {{ t("tenant.payments.outstanding") }}
            </div>
            <div
              :class="[
                'mt-1 text-display-sub font-semibold tabular-nums',
                outstanding > 0 ? 'text-status-overdue' : 'text-status-paid',
              ]"
            >
              {{ formatRM(outstanding) }}
            </div>
          </div>
          <span
            v-if="outstanding === 0"
            class="inline-flex items-center gap-1.5 text-caption text-status-paid"
          >
            <Icon name="CircleCheck" :size="16" />
            {{ t("tenant.payments.allSettled") }}
          </span>
        </div>
      </Card>

      <!-- Invoice cards -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div
          v-for="r in sorted"
          :key="r.invoice.id"
          role="button"
          tabindex="0"
          class="cursor-pointer rounded-lg border border-line-passive bg-surface-raised p-4 text-left outline-none transition hover:border-line-interactive focus-visible:shadow-focus"
          @click="open(r)"
          @keydown.enter.prevent="open(r)"
          @keydown.space.prevent="open(r)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="text-body font-medium text-ink">
                {{ periodLabel(r.invoice.dueDate) }}
              </div>
              <div class="text-caption tabular-nums text-ink-muted">
                {{ r.invoice.invoiceNumber }}
              </div>
            </div>
            <Pill v-if="isAwaiting(r)" tone="pending">
              {{ t("tenant.payments.status.awaiting") }}
            </Pill>
            <Pill v-else :tone="statusToneMap[r.invoice.status]">
              {{ t(`tenant.payments.status.${r.invoice.status}`) }}
            </Pill>
          </div>

          <div
            class="mt-3 flex items-end justify-between gap-3 border-t border-line-passive pt-3"
          >
            <div>
              <div class="text-card-title font-semibold tabular-nums text-ink">
                {{ formatRM(r.invoice.amount + r.invoice.lateFee) }}
              </div>
              <div class="text-caption tabular-nums text-ink-muted">
                <template v-if="isAwaiting(r)">
                  {{ t("tenant.payments.awaitingHint") }}
                </template>
                <template v-else-if="wasRejected(r)">
                  <span class="text-status-overdue">{{ t("tenant.payments.rejectedHint") }}</span>
                </template>
                <template v-else-if="isUnpaid(r)">
                  {{ t("tenant.payments.dueOn", { date: formatDate(r.invoice.dueDate) }) }}
                </template>
                <template v-else-if="r.payments[0]">
                  {{ t("tenant.payments.paidOn", { date: formatDate(r.payments[0].paidAt.slice(0, 10)) }) }}
                </template>
              </div>
            </div>
            <Button
              :variant="isUnpaid(r) && !isAwaiting(r) ? 'primary' : 'ghost'"
              size="sm"
              @click.stop="open(r)"
            >
              {{ isUnpaid(r) && !isAwaiting(r) ? t("tenant.payments.pay") : t("tenant.payments.view") }}
            </Button>
          </div>
        </div>
      </div>
    </template>

    <PayInvoiceModal v-model:open="showModal" :row="selected" @paid="refresh" />
  </div>
</template>
