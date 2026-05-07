<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("owner.nav.reports") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("owner.nav.reports") }}</h1>
    </header>
    <Card padding="loose">
      <EmptyState icon="ChartBar" title="Reports unlock with activity" description="Once you have agreements and payments, this is where annual income, occupancy, and tax reports live." />
    </Card>
  </div>
</template>
