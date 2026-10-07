<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import Modal from "~/components/ui/Modal.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import AgreementDocumentsPanel from "~/components/owner/AgreementDocumentsPanel.vue";
import { useToast } from "~/composables/useToast";
import type { AgreementWithRefs } from "~/services/useAgreements";

definePageMeta({ layout: "tenant" });
const { t } = useI18n();
const { formatRM } = useMoney();
const { show } = useToast();
const { tenantId } = useTenantSession();
const { public: { features } } = useRuntimeConfig();
const documentsEnabled = features.documents;
useHead({ title: () => t("tenant.nav.agreement") });

const row = ref<AgreementWithRefs | null>(null);
const loading = ref(true);

const load = async () => {
  if (tenantId.value) {
    row.value = await useAgreements().getActiveAgreementForTenant(tenantId.value);
  }
};

onMounted(async () => {
  try {
    await load();
  } finally {
    loading.value = false;
  }
});

// ── Review (spec 2026-10-07 agreement-review § 5): agree, or ask for changes ──
const showAgree = ref(false);
const showChanges = ref(false);
const note = ref("");
const acting = ref(false);
const isPending = computed(() => row.value?.agreement.status === "pending_review");
const isAccepted = computed(() => row.value?.agreement.status === "accepted");

const agree = async () => {
  if (!row.value || !tenantId.value) return;
  acting.value = true;
  try {
    await useAgreements().acceptForTenant(tenantId.value, row.value.agreement.id);
    await load();
    showAgree.value = false;
    show(t("tenant.agreement.review.agreedToast"), "success");
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    acting.value = false;
  }
};

const sendNote = async () => {
  if (!row.value || !tenantId.value || note.value.trim() === "") return;
  acting.value = true;
  try {
    await useAgreements().requestChangesForTenant(tenantId.value, row.value.agreement.id, note.value.trim());
    await load();
    showChanges.value = false;
    note.value = "";
    show(t("tenant.agreement.review.changesSentToast"), "success");
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    acting.value = false;
  }
};

const dayMs = 24 * 60 * 60 * 1000;
const termSummary = computed(() => {
  if (!row.value) return "";
  const start = new Date(row.value.agreement.startDate).getTime();
  const end = new Date(row.value.agreement.endDate).getTime();
  const months = Math.round((end - start) / dayMs / 30);
  if (months >= 12 && months % 12 === 0) {
    return t("tenant.agreement.years", { n: months / 12 });
  }
  return t("tenant.agreement.months", { n: months });
});

const formatDate = (iso: string) => {
  if (!iso) return "—";
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
};
</script>

<template>
  <div>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("tenant.agreement.title") }}
      </h1>
      <p class="mt-1 text-caption text-ink-muted">
        {{ t("tenant.agreement.subtitle") }}
      </p>
    </header>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <Card v-else-if="!row" padding="loose">
      <EmptyState
        icon="FileText"
        :title="t('tenant.home.noAgreementTitle')"
        :description="t('tenant.agreement.none')"
      />
    </Card>

    <div v-else class="space-y-4 sm:space-y-6">
      <!-- Review card: the tenant's answer to what the landlord sent -->
      <Card v-if="isPending" padding="loose" class="border-status-pending" data-testid="agreement-review">
        <div class="flex items-start gap-3">
          <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-pill bg-ink text-surface-page">
            <Icon name="FileSignature" :size="16" />
          </span>
          <div class="min-w-0 flex-1">
            <h2 class="text-card-title font-semibold text-ink">{{ t("tenant.agreement.review.title") }}</h2>
            <p class="mt-1 text-caption text-ink-muted">{{ t("tenant.agreement.review.subtitle") }}</p>
            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
              <Button variant="primary" :disabled="acting" @click="showAgree = true">
                <Icon name="Check" :size="14" class="mr-1" />
                {{ t("tenant.agreement.review.agree") }}
              </Button>
              <Button variant="ghost" :disabled="acting" @click="showChanges = true">
                <Icon name="MessageSquare" :size="14" class="mr-1" />
                {{ t("tenant.agreement.review.requestChanges") }}
              </Button>
            </div>
          </div>
        </div>
      </Card>

      <Card v-else-if="isAccepted" padding="loose" class="border-status-paid">
        <div class="flex items-start gap-3">
          <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-pill bg-status-paid-soft text-status-paid">
            <Icon name="CircleCheck" :size="16" />
          </span>
          <div>
            <h2 class="text-card-title font-semibold text-ink">{{ t("tenant.agreement.review.agreedTitle") }}</h2>
            <p class="mt-1 text-caption text-ink-muted">{{ t("tenant.agreement.review.agreedBody") }}</p>
          </div>
        </div>
      </Card>

      <!-- Summary -->
      <Card padding="loose">
        <div class="mb-5 flex flex-wrap items-center gap-2">
          <Pill :tone="row.agreement.status">
            {{ t(`tenant.agreement.status.${row.agreement.status}`) }}
          </Pill>
          <span class="text-caption text-ink-muted">
            <Icon name="Building2" :size="12" class="mr-1 inline" />
            {{ row.property?.name ?? "—" }} · {{ row.unit?.label ?? "—" }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
          <div class="rounded-md border border-line-passive bg-surface-page p-4">
            <div class="text-caption text-ink-muted">
              {{ t("tenant.agreement.term") }}
            </div>
            <div class="mt-1 text-card-title font-semibold tabular-nums text-ink">
              {{ termSummary }}
            </div>
          </div>
          <div class="rounded-md border border-line-passive bg-surface-page p-4">
            <div class="text-caption text-ink-muted">
              {{ t("tenant.agreement.monthlyRent") }}
            </div>
            <div class="mt-1 text-card-title font-semibold tabular-nums text-ink">
              {{ formatRM(row.agreement.rentAmount) }}
            </div>
          </div>
          <div class="rounded-md border border-line-passive bg-surface-page p-4">
            <div class="text-caption text-ink-muted">
              {{ t("tenant.agreement.deposit") }}
            </div>
            <div class="mt-1 text-card-title font-semibold tabular-nums text-ink">
              {{ formatRM(row.agreement.depositAmount) }}
            </div>
          </div>
          <div class="rounded-md border border-line-passive bg-surface-page p-4">
            <div class="text-caption text-ink-muted">
              {{ t("tenant.agreement.dueDay") }}
            </div>
            <div class="mt-1 text-card-title font-semibold tabular-nums text-ink">
              {{ t("tenant.agreement.dueOn", { day: row.agreement.rentDueDay }) }}
            </div>
          </div>
        </div>

        <section class="mt-6 space-y-3">
          <h3 class="text-caption font-semibold uppercase tracking-wide text-ink-muted">
            {{ t("tenant.agreement.term") }}
          </h3>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="rounded-md border border-line-passive bg-surface-page p-4">
              <div class="text-caption text-ink-muted">
                {{ t("tenant.agreement.startDate") }}
              </div>
              <div class="mt-1 text-body font-medium tabular-nums text-ink">
                {{ formatDate(row.agreement.startDate) }}
              </div>
            </div>
            <div class="rounded-md border border-line-passive bg-surface-page p-4">
              <div class="text-caption text-ink-muted">
                {{ t("tenant.agreement.endDate") }}
              </div>
              <div class="mt-1 text-body font-medium tabular-nums text-ink">
                {{ formatDate(row.agreement.endDate) }}
              </div>
            </div>
          </div>
        </section>

        <section class="mt-6 space-y-3">
          <h3 class="text-caption font-semibold uppercase tracking-wide text-ink-muted">
            {{ t("tenant.agreement.money") }}
          </h3>
          <div class="rounded-md border border-line-passive bg-surface-page p-4">
            <div class="flex items-baseline justify-between py-1 text-body">
              <span class="text-ink-muted">{{ t("tenant.agreement.lateFee") }}</span>
              <span class="tabular-nums text-ink">
                {{ formatRM(row.agreement.lateFee) }}
              </span>
            </div>
          </div>
          <p class="text-micro text-ink-faint">
            <Icon name="Info" :size="12" class="mr-1 inline" />
            {{ t("tenant.agreement.lateFeeHint") }}
          </p>
        </section>
      </Card>

      <!-- Documents (Phase-4 placeholder) -->
      <Card v-if="documentsEnabled" padding="loose">
        <h2 class="mb-4 text-card-title font-semibold text-ink">
          {{ t("tenant.agreement.documents") }}
        </h2>
        <AgreementDocumentsPanel />
      </Card>
    </div>

    <!-- Agree: restate the money terms before committing -->
    <Modal :open="showAgree" :title="t('tenant.agreement.review.confirmTitle')" size="md" @update:open="showAgree = $event">
      <p v-if="row" class="text-body text-ink">
        {{ t("tenant.agreement.review.confirmBody", {
          unit: `${row.property?.name ?? ""} · ${row.unit?.label ?? ""}`,
          rent: formatRM(row.agreement.rentAmount),
          start: formatDate(row.agreement.startDate),
          end: formatDate(row.agreement.endDate),
          deposit: formatRM(row.agreement.depositAmount),
        }) }}
      </p>
      <template #footer>
        <Button variant="ghost" :disabled="acting" @click="showAgree = false">{{ t("common.cancel") }}</Button>
        <Button variant="primary" :loading="acting" @click="agree">{{ t("tenant.agreement.review.confirmCta") }}</Button>
      </template>
    </Modal>

    <!-- Ask for changes: a short note the landlord sees on the agreement -->
    <Modal :open="showChanges" :title="t('tenant.agreement.review.changesTitle')" size="md" @update:open="showChanges = $event">
      <form id="agreement-changes-form" class="space-y-3" @submit.prevent="sendNote">
        <p class="text-caption text-ink-muted">{{ t("tenant.agreement.review.changesHint") }}</p>
        <label class="block">
          <span class="mb-1 block text-caption font-medium text-ink">{{ t("tenant.agreement.review.noteLabel") }}</span>
          <textarea
            v-model="note"
            rows="4"
            maxlength="500"
            required
            :placeholder="t('tenant.agreement.review.notePlaceholder')"
            class="w-full rounded-md border border-line-passive bg-surface-page px-3 py-2 text-body text-ink outline-none transition placeholder:text-ink-muted focus-visible:shadow-focus"
          />
        </label>
      </form>
      <template #footer>
        <Button variant="ghost" :disabled="acting" @click="showChanges = false">{{ t("common.cancel") }}</Button>
        <Button type="submit" form="agreement-changes-form" variant="primary" :loading="acting" :disabled="note.trim() === ''">
          {{ t("tenant.agreement.review.sendNote") }}
        </Button>
      </template>
    </Modal>
  </div>
</template>
