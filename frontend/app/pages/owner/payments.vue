<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("owner.nav.payments") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("owner.nav.payments") }}</h1>
    </header>
    <Card padding="loose">
      <EmptyState icon="Receipt" title="No invoices yet" description="Invoices generate automatically when an agreement activates." />
    </Card>
  </div>
</template>
