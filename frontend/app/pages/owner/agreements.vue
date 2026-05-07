<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("owner.nav.agreements") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("owner.nav.agreements") }}</h1>
    </header>
    <Card padding="loose">
      <EmptyState icon="FileText" title="No agreements yet" description="Create an agreement once you have a tenant in a unit." />
    </Card>
  </div>
</template>
