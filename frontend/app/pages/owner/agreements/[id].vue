<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { TabsRoot, TabsList, TabsTrigger, TabsContent } from "reka-ui";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import Modal from "~/components/ui/Modal.vue";
import Select from "~/components/ui/Select.vue";
import AgreementOverviewPanel from "~/components/owner/AgreementOverviewPanel.vue";
import AgreementTermsForm from "~/components/owner/AgreementTermsForm.vue";
import AgreementDocumentsPanel from "~/components/owner/AgreementDocumentsPanel.vue";
import { useToast } from "~/composables/useToast";
import type { Agreement } from "~/types/agreement";
import type { AgreementWithRefs } from "~/services/useAgreements";

definePageMeta({ layout: "owner" });
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { show } = useToast();
const { public: { features } } = useRuntimeConfig();
const documentsEnabled = features.documents;

const row = ref<AgreementWithRefs | null>(null);
const loading = ref(true);
const showDeleteConfirm = ref(false);
const deleting = ref(false);
const activeTab = ref<string>("overview");

const load = async () => {
  loading.value = true;
  try {
    const all = await useAgreements().getAgreementsWithRefs();
    row.value = all.find((r) => r.agreement.id === route.params.id) ?? null;
  } finally {
    loading.value = false;
  }
};

onMounted(load);

useHead({
  title: () =>
    row.value?.tenant?.name ?? t("owner.nav.agreements"),
});

const onSaved = async (updated: Agreement) => {
  if (!row.value) return;
  const wasUnderReview = row.value.agreement.status === "pending_review" || row.value.agreement.status === "accepted";
  // Re-hydrate refs in case the unit/tenant changed.
  await load();
  // Fallback: at minimum, swap in the updated agreement so UI reflects edits
  // even if the row vanished from the listWithRefs result.
  if (row.value && row.value.agreement.id === updated.id) {
    row.value = { ...row.value, agreement: updated };
  }
  // A term edit while sent / accepted voids the tenant's answer (spec 2026-10-07 agreement-review § 3).
  if (wasUnderReview && updated.status === "draft") {
    show(t("owner.agreements.review.backToDraftToast"), "default");
  }
};

// ── Review flow: send → tenant answers → owner activates ────────────────────
const acting = ref(false);
const applyAgreement = (updated: Agreement) => {
  if (row.value) row.value = { ...row.value, agreement: updated };
};
const sendAgreement = async () => {
  if (!row.value) return;
  acting.value = true;
  try {
    applyAgreement(await useAgreements().send(row.value.agreement.id));
    show(t("owner.agreements.review.sentToast", { name: row.value.tenant?.name ?? "" }), "success");
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    acting.value = false;
  }
};
const withdrawAgreement = async () => {
  if (!row.value) return;
  acting.value = true;
  try {
    applyAgreement(await useAgreements().withdraw(row.value.agreement.id));
    show(t("owner.agreements.review.withdrawnToast"), "default");
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    acting.value = false;
  }
};
const activateAgreement = async () => {
  if (!row.value) return;
  acting.value = true;
  try {
    applyAgreement(await useAgreements().update(row.value.agreement.id, { status: "active" }));
    show(t("owner.agreements.review.activatedToast"), "success");
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    acting.value = false;
  }
};

const confirmDelete = async () => {
  if (!row.value) return;
  deleting.value = true;
  try {
    await useAgreements().remove(row.value.agreement.id);
    show(t("owner.agreements.deletedToast"), "success");
    router.push("/owner/agreements");
  } finally {
    deleting.value = false;
  }
};

const tabOptions = computed(() => {
  const base = [
    { value: "overview", label: t("owner.agreements.detail.tabs.overview") },
    { value: "terms", label: t("owner.agreements.detail.tabs.terms") },
  ];
  if (documentsEnabled) {
    base.push({
      value: "documents",
      label: t("owner.agreements.detail.tabs.documents"),
    });
  }
  return base;
});

const tabTriggerClass =
  "-mb-px inline-flex items-center border-b-2 border-transparent px-4 py-2 text-body text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus data-[state=active]:border-ink data-[state=active]:text-ink";

const formatDate = (iso: string) => {
  if (!iso) return "—";
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
};
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between gap-2">
      <NuxtLink
        to="/owner/agreements"
        class="inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink"
      >
        <Icon name="ArrowLeft" :size="14" />
        {{ t("owner.agreements.detail.back") }}
      </NuxtLink>
    </div>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("common.loading") }}
      </p>
    </Card>

    <Card v-else-if="!row" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("owner.agreements.detail.notFound") }}
      </p>
    </Card>

    <template v-else>
      <header class="mb-6 sm:flex sm:items-end sm:justify-between sm:gap-4">
        <div class="sm:min-w-0">
          <Pill :tone="row.agreement.status" class="mb-3">
            {{ t(`owner.agreements.status.${row.agreement.status}`) }}
          </Pill>
          <h1 class="text-display-sub font-semibold tracking-snug text-ink">
            {{ row.tenant?.name ?? t("owner.agreements.unknownTenant") }}
          </h1>
          <p class="mt-2 text-caption text-ink-muted">
            <Icon name="Building2" :size="12" class="mr-1 inline" />
            {{ row.property?.name ?? "—" }} · {{ row.unit?.label ?? "—" }}
            <span class="text-ink-faint">
              · {{ formatDate(row.agreement.startDate) }} →
              {{ formatDate(row.agreement.endDate) }}
            </span>
          </p>
        </div>
        <!-- Review-flow actions + icon-only delete (spec 2026-10-07 agreement-review § 5) -->
        <div class="mt-4 flex shrink-0 flex-wrap items-center gap-2 sm:mt-0">
          <Button
            v-if="row.agreement.status === 'draft'"
            variant="primary"
            size="sm"
            :loading="acting"
            @click="sendAgreement"
          >
            <Icon name="Send" :size="14" class="mr-1" />
            {{ t("owner.agreements.review.send") }}
          </Button>
          <Button
            v-else-if="row.agreement.status === 'pending_review'"
            variant="ghost"
            size="sm"
            :loading="acting"
            @click="withdrawAgreement"
          >
            <Icon name="Undo2" :size="14" class="mr-1" />
            {{ t("owner.agreements.review.withdraw") }}
          </Button>
          <Button
            v-else-if="row.agreement.status === 'accepted'"
            variant="primary"
            size="sm"
            :loading="acting"
            @click="activateAgreement"
          >
            <Icon name="CircleCheck" :size="14" class="mr-1" />
            {{ t("owner.agreements.review.activate") }}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            class="!px-2"
            :aria-label="t('owner.agreements.review.deleteAria')"
            :title="t('owner.agreements.review.deleteAria')"
            :disabled="acting"
            @click="showDeleteConfirm = true"
          >
            <Icon name="Trash2" :size="16" />
          </Button>
        </div>
      </header>

      <Card padding="loose">
        <TabsRoot v-model="activeTab">
          <div class="mb-6 sm:hidden">
            <Select v-model="activeTab" :options="tabOptions" />
          </div>
          <TabsList
            class="mb-6 hidden flex-wrap gap-1 border-b border-line-passive sm:flex"
          >
            <TabsTrigger value="overview" :class="tabTriggerClass">
              {{ t("owner.agreements.detail.tabs.overview") }}
            </TabsTrigger>
            <TabsTrigger value="terms" :class="tabTriggerClass">
              {{ t("owner.agreements.detail.tabs.terms") }}
            </TabsTrigger>
            <TabsTrigger
              v-if="documentsEnabled"
              value="documents"
              :class="tabTriggerClass"
            >
              {{ t("owner.agreements.detail.tabs.documents") }}
              <span
                class="ml-1.5 h-1.5 w-1.5 rounded-full bg-line-passive"
                :title="t('owner.agreements.detail.documents.placeholderTitle')"
              />
            </TabsTrigger>
          </TabsList>

          <TabsContent value="overview" class="outline-none">
            <AgreementOverviewPanel :row="row" @updated="load" />
          </TabsContent>
          <TabsContent value="terms" class="outline-none">
            <AgreementTermsForm
              :agreement="row.agreement"
              mode="edit"
              @saved="onSaved"
            />
          </TabsContent>
          <TabsContent
            v-if="documentsEnabled"
            value="documents"
            class="outline-none"
          >
            <AgreementDocumentsPanel />
          </TabsContent>
        </TabsRoot>
      </Card>

      <Modal
        v-model:open="showDeleteConfirm"
        :title="t('owner.agreements.deleteConfirm.title')"
        size="sm"
      >
        <p class="text-body text-ink">
          {{ t("owner.agreements.deleteConfirm.body") }}
        </p>
        <template #footer>
          <Button
            variant="ghost"
            :disabled="deleting"
            @click="showDeleteConfirm = false"
          >
            {{ t("common.cancel") }}
          </Button>
          <Button
            variant="accent"
            :loading="deleting"
            @click="confirmDelete"
          >
            {{ t("owner.agreements.deleteConfirm.confirm") }}
          </Button>
        </template>
      </Modal>
    </template>
  </div>
</template>
