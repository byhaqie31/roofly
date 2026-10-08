<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";

defineProps<{
  loading: boolean;
  empty: boolean;
  emptyTitle: string;
  emptyDescription?: string;
  page: number;
  lastPage: number;
  total: number;
}>();
defineEmits<{ "update:page": [page: number] }>();
const { t } = useI18n();
</script>

<template>
  <Card v-if="loading" padding="loose">
    <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
  </Card>
  <Card v-else-if="empty" padding="loose">
    <EmptyState icon="SearchX" :title="emptyTitle" :description="emptyDescription" />
  </Card>
  <template v-else>
    <!-- Desktop: the TanStack table. Mobile: card rows (UI-STANDARDS § 11.14). -->
    <Card padding="compact" class="hidden sm:block overflow-x-auto">
      <slot name="table" />
    </Card>
    <div class="sm:hidden space-y-3">
      <slot name="cards" />
    </div>
    <footer class="mt-4 flex items-center justify-between gap-3 text-caption text-ink-muted">
      <span>{{ t("admin.common.pageOf", { page, lastPage, total }) }}</span>
      <div class="flex gap-2">
        <Button variant="ghost" size="sm" :disabled="page <= 1" @click="$emit('update:page', page - 1)">{{ t("common.back") }}</Button>
        <Button variant="ghost" size="sm" :disabled="page >= lastPage" @click="$emit('update:page', page + 1)">{{ t("common.next") }}</Button>
      </div>
    </footer>
  </template>
</template>
