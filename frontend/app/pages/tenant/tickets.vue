<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

definePageMeta({ layout: "tenant" });
const { t } = useI18n();
useHead({ title: () => t("tenant.nav.tickets") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("tenant.nav.tickets") }}</h1>
    </header>
    <Card padding="loose">
      <EmptyState icon="Wrench" title="No issues reported" description="Report an issue any time and we'll notify your landlord." />
    </Card>
  </div>
</template>
