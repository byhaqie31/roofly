<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

definePageMeta({ layout: "tenant" });
const { t } = useI18n();
useHead({ title: () => t("tenant.nav.agreement") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("tenant.nav.agreement") }}</h1>
    </header>
    <Card padding="loose">
      <EmptyState icon="FileText" :title="t('tenant.home.noActiveAgreement')" />
    </Card>
  </div>
</template>
