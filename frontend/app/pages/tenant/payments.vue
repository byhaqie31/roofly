<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

definePageMeta({ layout: "tenant" });
const { t } = useI18n();
useHead({ title: () => t("tenant.nav.payments") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("tenant.nav.payments") }}</h1>
    </header>
    <Card padding="loose">
      <EmptyState icon="Receipt" title="No payments yet" description="Your invoices and payment receipts will show up here." />
    </Card>
  </div>
</template>
