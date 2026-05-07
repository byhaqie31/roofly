<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("owner.nav.tenants") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("owner.nav.tenants") }}</h1>
    </header>
    <Card padding="loose">
      <EmptyState icon="Users" title="No tenants yet" description="Tenants appear here once you invite them to a unit." />
    </Card>
  </div>
</template>
