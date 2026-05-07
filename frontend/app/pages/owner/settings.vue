<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("owner.nav.settings") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("owner.nav.settings") }}</h1>
    </header>
    <Card padding="loose">
      <EmptyState icon="Settings" title="Settings coming soon" description="Profile, business details, bank info, agreement templates, and notification preferences will live here." />
    </Card>
  </div>
</template>
