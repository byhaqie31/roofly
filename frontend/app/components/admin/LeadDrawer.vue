<script setup lang="ts">
import { ref, watch } from "vue";
import Modal from "~/components/ui/Modal.vue";
import SourcePill from "~/components/admin/SourcePill.vue";
import EventList from "~/components/admin/EventList.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import { formatAdminDateTime } from "~/utils/adminDate";
import type { AdminLeadDetail } from "~/types/analytics";

const props = defineProps<{ open: boolean; leadId: string | null }>();
const emit = defineEmits<{ "update:open": [v: boolean] }>();
const { t } = useI18n();

const lead = ref<AdminLeadDetail | null>(null);
const loading = ref(false);

const fmt = formatAdminDateTime;

let requestSeq = 0;

watch(
  () => [props.open, props.leadId] as const,
  async ([isOpen, id]) => {
    if (!isOpen || !id) {
      lead.value = null;
      return;
    }
    const seq = ++requestSeq;
    lead.value = null;
    loading.value = true;
    try {
      const res = await useAdminAnalytics().lead(id);
      if (seq !== requestSeq) return;
      lead.value = res;
    } finally {
      if (seq === requestSeq) loading.value = false;
    }
  },
  { immediate: true },
);
</script>

<template>
  <Modal :open="open" :title="t('admin.analytics.drawer.title')" size="lg" @update:open="emit('update:open', $event)">
    <div v-if="loading" class="py-10 text-center text-caption text-ink-muted">{{ t("common.loading") }}</div>
    <div v-else-if="lead">
      <div class="flex flex-wrap items-center gap-3">
        <p class="text-body font-medium text-ink">{{ lead.email }}</p>
        <SourcePill :source="lead.source" />
      </div>
      <p class="mt-1 text-micro text-ink-muted">{{ fmt(lead.firstSeenAt) }} – {{ fmt(lead.lastSeenAt) }}</p>
      <p v-if="lead.convertedUserId" class="mt-2 text-caption">
        <NuxtLink :to="`/admin/owners/${lead.convertedUserId}`" class="text-ink underline underline-offset-2">{{ lead.convertedOwnerName }}</NuxtLink>
      </p>
      <h4 class="mb-2 mt-6 text-caption font-semibold text-ink-strong">{{ t("admin.analytics.drawer.recent") }}</h4>
      <EventList :events="lead.events" />
    </div>
    <EmptyState v-else icon="UserX" :title="t('admin.common.notFound')" />
  </Modal>
</template>
