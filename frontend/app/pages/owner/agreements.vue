<script setup lang="ts">
import { onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";
import AgreementCard from "~/components/owner/AgreementCard.vue";
import AgreementFormModal from "~/components/owner/AgreementFormModal.vue";
import type { Agreement } from "~/types/agreement";
import type { AgreementWithRefs } from "~/services/useAgreements";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("owner.nav.agreements") });

const rows = ref<AgreementWithRefs[]>([]);
const loading = ref(true);
const showModal = ref(false);
const editingAgreement = ref<Agreement | null>(null);

const refresh = async () => {
  rows.value = await useAgreements().listWithRefs();
};

onMounted(async () => {
  try {
    await refresh();
  } finally {
    loading.value = false;
  }
});

const onAdd = () => {
  editingAgreement.value = null;
  showModal.value = true;
};

const onEdit = (row: AgreementWithRefs) => {
  editingAgreement.value = row.agreement;
  showModal.value = true;
};

const onSaved = async () => {
  await refresh();
};

const onDeleted = async () => {
  await refresh();
};
</script>

<template>
  <div>
    <header class="mb-8 flex items-end justify-between">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.nav.agreements") }}
      </h1>
      <Button variant="primary" @click="onAdd">
        + {{ t("owner.agreements.addCta") }}
      </Button>
    </header>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("common.loading") }}
      </p>
    </Card>

    <Card v-else-if="rows.length === 0" padding="loose">
      <EmptyState
        icon="FileText"
        :title="t('owner.agreements.emptyTitle')"
        :description="t('owner.agreements.emptyDescription')"
      />
    </Card>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <AgreementCard
        v-for="row in rows"
        :key="row.agreement.id"
        :row="row"
        @click="onEdit(row)"
      />
    </div>

    <AgreementFormModal
      v-model:open="showModal"
      :agreement="editingAgreement"
      @saved="onSaved"
      @deleted="onDeleted"
    />
  </div>
</template>
