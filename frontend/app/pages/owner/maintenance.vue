<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("owner.nav.maintenance") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("owner.nav.maintenance") }}</h1>
    </header>
    <Card padding="loose">
      <EmptyState icon="Wrench" title="No tickets yet" description="Tenants can report issues from their app once they're invited." />
    </Card>
  </div>
</template>
