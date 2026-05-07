<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

definePageMeta({ layout: "tenant" });
const { t } = useI18n();
useHead({ title: () => t("tenant.nav.profile") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("tenant.nav.profile") }}</h1>
    </header>
    <Card padding="loose">
      <EmptyState icon="User" title="Profile" description="Personal info, IC copy, and emergency contact will live here." />
    </Card>
  </div>
</template>
