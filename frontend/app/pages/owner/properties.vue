<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("owner.nav.properties") });
</script>

<template>
  <div>
    <header class="mb-8 flex items-end justify-between">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.nav.properties") }}
      </h1>
      <Button variant="primary">+ {{ t("owner.dashboard.emptyState.cta") }}</Button>
    </header>
    <Card padding="loose">
      <EmptyState
        icon="Building2"
        :title="t('owner.dashboard.emptyState.title')"
        :description="t('owner.dashboard.emptyState.description')"
      />
    </Card>
  </div>
</template>
