<script setup lang="ts">
import { onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";
import AgreementCard from "~/components/owner/AgreementCard.vue";
import type { AgreementWithRefs } from "~/services/useAgreements";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("owner.nav.agreements") });

const rows = ref<AgreementWithRefs[]>([]);
const loading = ref(true);

onMounted(async () => {
  try {
    rows.value = await useAgreements().listWithRefs();
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div>
    <header
      class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-end sm:justify-between"
    >
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.nav.agreements") }}
      </h1>
      <NuxtLink to="/owner/agreements/new" class="self-start">
        <Button variant="primary">
          + {{ t("owner.agreements.addCta") }}
        </Button>
      </NuxtLink>
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
      />
    </div>
  </div>
</template>
