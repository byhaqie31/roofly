<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import PayInvoiceModal from "~/components/tenant/PayInvoiceModal.vue";
import ProfileNudgeCard from "~/components/tenant/ProfileNudgeCard.vue";
import AgreementReviewCard from "~/components/tenant/AgreementReviewCard.vue";
import { tenantProfileGaps, type TenantProfileGaps } from "~/utils/tenantProfileCompletion";
import type { AgreementWithRefs } from "~/services/useAgreements";
import type { InvoiceWithRefs } from "~/services/useInvoices";
import type { TicketWithRefs } from "~/services/useTickets";

definePageMeta({ layout: "tenant" });
const { t } = useI18n();
const { formatRM } = useMoney();
const auth = useAuthStore();
const { tenantId } = useTenantSession();
const demoTour = useDemoTour("tenant");
useHead({ title: () => t("tenant.nav.home") });

const agreement = ref<AgreementWithRefs | null>(null);
const invoices = ref<InvoiceWithRefs[]>([]);
const tickets = ref<TicketWithRefs[]>([]);
const loading = ref(true);
const payRow = ref<InvoiceWithRefs | null>(null);
const showPay = ref(false);
// Core-profile gaps drive the "Complete your profile" card (spec 2026-10-07 § 4.4).
const profileGaps = ref<TenantProfileGaps | null>(null);

const loadInvoices = async () => {
  if (!tenantId.value) return;
  invoices.value = await useInvoices().getInvoicesForTenant(tenantId.value);
};

const load = async () => {
  loading.value = true;
  try {
    const id = tenantId.value;
    if (!id) return;
    const [a, , tk, profile] = await Promise.all([
      useAgreements().getActiveAgreementForTenant(id),
      loadInvoices(),
      useTickets().getTicketsForTenant(id),
      useTenants().getProfile(id),
    ]);
    agreement.value = a;
    tickets.value = tk;
    profileGaps.value = tenantProfileGaps(profile);
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  await load();
  demoTour.maybeAutoStart();
});

const firstName = computed(() => (auth.user?.name ?? "").split(" ")[0] ?? "");

// Earliest unpaid invoice — the tenant's next action.
const nextInvoice = computed<InvoiceWithRefs | null>(() => {
  const unpaid = invoices.value
    .filter(
      (r) => r.invoice.status === "pending" || r.invoice.status === "overdue",
    )
    .sort((a, b) => a.invoice.dueDate.localeCompare(b.invoice.dueDate));
  return unpaid[0] ?? null;
});

const nextDueDate = computed<string | null>(() => {
  const upcoming = invoices.value
    .filter((r) => r.invoice.status === "pending")
    .sort((a, b) => a.invoice.dueDate.localeCompare(b.invoice.dueDate));
  return upcoming[0]?.invoice.dueDate ?? null;
});

const nextTotal = computed(() =>
  nextInvoice.value
    ? nextInvoice.value.invoice.amount + nextInvoice.value.invoice.lateFee
    : 0,
);

const openIssues = computed(() =>
  tickets.value
    .filter((r) => r.ticket.status !== "resolved")
    .sort((a, b) => b.ticket.updatedAt.localeCompare(a.ticket.updatedAt)),
);

const dayMs = 24 * 60 * 60 * 1000;
const daysLeft = computed(() => {
  if (!agreement.value) return null;
  const end = new Date(agreement.value.agreement.endDate).getTime();
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return Math.round((end - today.getTime()) / dayMs);
});

const statusTone = (s: string) => {
  switch (s) {
    case "new":
      return "pending";
    case "in_progress":
      return "active";
    case "resolved":
      return "paid";
    case "reopened":
      return "overdue";
    default:
      return "neutral";
  }
};

const formatDate = (iso: string) => {
  if (!iso) return "—";
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
};

const onPay = (row: InvoiceWithRefs) => {
  payRow.value = row;
  showPay.value = true;
};

const onPaid = async () => {
  await loadInvoices();
};
</script>

<template>
  <div>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ firstName ? t("tenant.home.greeting", { name: firstName }) : t("tenant.home.title") }}
      </h1>
      <p class="mt-1 text-caption text-ink-muted">
        {{ t("tenant.home.subtitle") }}
      </p>
    </header>

    <!-- Profile nudge comes first, with or without an agreement -->
    <ProfileNudgeCard v-if="!loading && profileGaps && profileGaps.missing.length > 0" :gaps="profileGaps" />
    <AgreementReviewCard v-if="!loading && agreement?.agreement.status === 'pending_review'" />

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <Card v-else-if="!agreement" padding="loose">
      <EmptyState
        icon="DoorOpen"
        :title="t('tenant.home.noAgreementTitle')"
        :description="t('tenant.home.noActiveAgreement')"
      />
    </Card>

    <template v-else>
      <!-- Rent-due hero -->
      <Card data-tour="rent" padding="loose" class="mb-4 sm:mb-6">
        <div
          class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-caption text-ink-muted">
                {{
                  nextInvoice
                    ? t("tenant.home.rentDue.title")
                    : t("tenant.home.rentDue.allPaidTitle")
                }}
              </span>
              <Pill
                v-if="nextInvoice"
                :tone="nextInvoice.invoice.status === 'overdue' ? 'overdue' : 'pending'"
              >
                {{ t(`tenant.payments.status.${nextInvoice.invoice.status}`) }}
              </Pill>
            </div>

            <div
              v-if="nextInvoice"
              class="mt-1 text-display-sub font-semibold tracking-snug tabular-nums text-ink"
            >
              {{ formatRM(nextTotal) }}
            </div>
            <div v-else class="mt-1 flex items-center gap-2 text-ink">
              <Icon name="CircleCheck" :size="20" class="text-status-paid" />
              <span class="text-card-title font-semibold">
                {{ t("tenant.home.rentDue.allPaid") }}
              </span>
            </div>

            <p class="mt-1 text-caption text-ink-muted tabular-nums">
              <template v-if="nextInvoice">
                {{
                  nextInvoice.invoice.status === "overdue"
                    ? t("tenant.home.rentDue.overdueOn", {
                        date: formatDate(nextInvoice.invoice.dueDate),
                      })
                    : t("tenant.home.rentDue.dueOn", {
                        date: formatDate(nextInvoice.invoice.dueDate),
                      })
                }}
              </template>
              <template v-else-if="nextDueDate">
                {{ t("tenant.home.rentDue.allPaidHelp", { date: formatDate(nextDueDate) }) }}
              </template>
              <template v-else>
                {{ t("tenant.home.rentDue.noUpcoming") }}
              </template>
            </p>
          </div>

          <Button
            v-if="nextInvoice"
            variant="primary"
            size="lg"
            class="shrink-0 self-start sm:self-auto"
            @click="onPay(nextInvoice)"
          >
            <Icon name="CreditCard" :size="16" class="mr-1.5" />
            {{ t("tenant.home.payNow") }}
          </Button>
        </div>
      </Card>

      <!-- Stat tiles -->
      <div class="mb-4 grid grid-cols-2 gap-3 sm:mb-6 sm:grid-cols-4 sm:gap-4">
        <div class="rounded-lg border border-line-passive bg-surface-raised p-4">
          <div class="text-caption text-ink-muted">
            {{ t("tenant.home.stats.monthlyRent") }}
          </div>
          <div class="mt-1 text-card-title font-semibold tabular-nums text-ink">
            {{ formatRM(agreement.agreement.rentAmount) }}
          </div>
        </div>
        <div class="rounded-lg border border-line-passive bg-surface-raised p-4">
          <div class="text-caption text-ink-muted">
            {{ t("tenant.home.stats.depositHeld") }}
          </div>
          <div class="mt-1 text-card-title font-semibold tabular-nums text-ink">
            {{ formatRM(agreement.agreement.depositAmount) }}
          </div>
        </div>
        <div class="rounded-lg border border-line-passive bg-surface-raised p-4">
          <div class="text-caption text-ink-muted">
            {{ t("tenant.home.stats.agreementEnds") }}
          </div>
          <div class="mt-1 text-body font-semibold tabular-nums text-ink">
            {{ formatDate(agreement.agreement.endDate) }}
          </div>
          <div
            v-if="daysLeft !== null"
            class="text-micro tabular-nums"
            :class="daysLeft <= 60 ? 'text-status-expired' : 'text-ink-faint'"
          >
            {{
              daysLeft >= 0
                ? t("tenant.home.stats.daysLeft", { n: daysLeft })
                : t("tenant.home.stats.endedAgo", { n: -daysLeft })
            }}
          </div>
        </div>
        <div class="rounded-lg border border-line-passive bg-surface-raised p-4">
          <div class="text-caption text-ink-muted">
            {{ t("tenant.home.stats.openIssues") }}
          </div>
          <div class="mt-1 text-card-title font-semibold tabular-nums text-ink">
            {{ openIssues.length }}
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
        <!-- Your home -->
        <Card padding="loose" class="lg:col-span-2">
          <h2 class="mb-4 text-card-title font-semibold text-ink">
            {{ t("tenant.home.yourHome") }}
          </h2>
          <div class="flex items-start gap-3">
            <span
              class="mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-surface-page text-ink-muted"
            >
              <Icon name="Building2" :size="20" />
            </span>
            <div class="min-w-0">
              <div class="text-body font-medium text-ink">
                {{ agreement.property?.name ?? "—" }}
              </div>
              <div class="text-caption text-ink-muted">
                {{ agreement.unit?.label ?? "—" }}
              </div>
              <div v-if="agreement.property" class="mt-1 text-caption text-ink-muted">
                {{ agreement.property.address }}, {{ agreement.property.postcode }}
                {{ agreement.property.city }}, {{ agreement.property.state }}
              </div>
            </div>
          </div>

          <div data-tour="actions" class="mt-6 flex flex-wrap gap-2 border-t border-line-passive pt-4">
            <NuxtLink to="/tenant/agreement">
              <Button variant="ghost" size="sm">
                <Icon name="FileText" :size="14" class="mr-1.5" />
                {{ t("tenant.home.viewAgreement") }}
              </Button>
            </NuxtLink>
            <NuxtLink to="/tenant/payments">
              <Button variant="ghost" size="sm">
                <Icon name="Receipt" :size="14" class="mr-1.5" />
                {{ t("tenant.home.viewPayments") }}
              </Button>
            </NuxtLink>
            <NuxtLink to="/tenant/tickets">
              <Button variant="ghost" size="sm">
                <Icon name="Wrench" :size="14" class="mr-1.5" />
                {{ t("tenant.home.reportIssue") }}
              </Button>
            </NuxtLink>
          </div>
        </Card>

        <!-- Open issues -->
        <Card data-tour="issues" padding="loose">
          <header class="mb-4 flex items-center justify-between">
            <h2 class="text-card-title font-semibold text-ink">
              {{ t("tenant.home.openIssuesTitle") }}
            </h2>
            <NuxtLink
              v-if="tickets.length > 0"
              to="/tenant/tickets"
              class="text-caption text-ink-muted underline-offset-2 transition hover:text-ink hover:underline"
            >
              {{ t("tenant.home.viewAll") }}
            </NuxtLink>
          </header>

          <ul v-if="openIssues.length > 0" class="space-y-2">
            <li v-for="r in openIssues.slice(0, 4)" :key="r.ticket.id">
              <NuxtLink
                :to="`/tenant/tickets/${r.ticket.id}`"
                class="block rounded-md border border-line-passive bg-surface-page p-3 outline-none transition hover:border-line-interactive focus-visible:shadow-focus"
              >
                <div class="flex items-center gap-2">
                  <Pill :tone="statusTone(r.ticket.status)">
                    {{ t(`tenant.tickets.status.${r.ticket.status}`) }}
                  </Pill>
                  <span class="text-micro text-ink-faint">
                    {{ t(`tenant.tickets.category.${r.ticket.category}`) }}
                  </span>
                </div>
                <p class="mt-1.5 truncate text-caption font-medium text-ink">
                  {{ r.ticket.title }}
                </p>
              </NuxtLink>
            </li>
          </ul>

          <p v-else class="py-2 text-caption text-ink-muted">
            {{ t("tenant.home.noOpenIssues") }}
          </p>
        </Card>
      </div>
    </template>

    <PayInvoiceModal v-model:open="showPay" :row="payRow" @paid="onPaid" />
  </div>
</template>
