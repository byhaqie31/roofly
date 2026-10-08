<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import type { AdminPermission } from "~/types/admin";

/**
 * Shown instead of a page (or section) the signed-in admin lacks the
 * permission for. The API still enforces — this just replaces an empty
 * table / silent 403 with a clear message naming the missing key.
 */
defineProps<{ permission: AdminPermission }>();
const { t } = useI18n();
</script>

<template>
  <Card padding="loose">
    <EmptyState
      icon="Lock"
      :title="t('admin.common.noAccess')"
      :description="t('admin.common.noAccessHelp', { permission })"
    />
  </Card>
</template>
