<script setup lang="ts">
import { useRouter } from "vue-router";
import Card from "~/components/ui/Card.vue";
import Icon from "~/components/ui/Icon.vue";
import AgreementTermsForm from "~/components/owner/AgreementTermsForm.vue";
import type { Agreement } from "~/types/agreement";

definePageMeta({ layout: "owner" });
const router = useRouter();
const { t } = useI18n();
useHead({ title: () => t("owner.agreements.addTitle") });

const onSaved = (created: Agreement) => {
  router.push(`/owner/agreements/${created.id}`);
};
</script>

<template>
  <div>
    <NuxtLink
      to="/owner/agreements"
      class="mb-6 inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink"
    >
      <Icon name="ArrowLeft" :size="14" />
      {{ t("owner.agreements.detail.back") }}
    </NuxtLink>

    <header class="mb-6">
      <h1 class="text-display-sub font-semibold tracking-snug text-ink">
        {{ t("owner.agreements.addTitle") }}
      </h1>
      <p class="mt-2 text-caption text-ink-muted">
        {{ t("owner.agreements.detail.newSubtitle") }}
      </p>
    </header>

    <Card padding="loose">
      <AgreementTermsForm mode="create" @saved="onSaved" />
    </Card>
  </div>
</template>
