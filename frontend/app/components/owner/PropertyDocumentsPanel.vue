<script setup lang="ts">
import EmptyState from "~/components/ui/EmptyState.vue";

const { t } = useI18n();
</script>

<template>
  <div class="space-y-4">
    <p class="text-caption text-ink-muted">
      {{ t("owner.properties.detail.documents.help") }}
    </p>
    <div class="rounded-md border border-dashed border-line-passive bg-surface-page p-6">
      <EmptyState
        icon="FileText"
        :title="t('owner.properties.detail.documents.placeholderTitle')"
        :description="t('owner.properties.detail.documents.placeholderHelp')"
      />
    </div>
  </div>
</template>
