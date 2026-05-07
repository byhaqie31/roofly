<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

definePageMeta({ layout: "tenant" });
const { t } = useI18n();
useHead({ title: () => t("tenant.home.title") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("tenant.home.title") }}</h1>
    </header>
    <Card padding="loose">
      <EmptyState icon="DoorOpen" :title="t('tenant.home.noActiveAgreement')" />
    </Card>
  </div>
</template>
