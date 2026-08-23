<script setup lang="ts">
import Pill from "~/components/ui/Pill.vue";
import type { AdminAttentionItem, AdminAttentionKind } from "~/types/admin";

defineProps<{ items: AdminAttentionItem[] }>();
const { t } = useI18n();

const tone: Record<AdminAttentionKind, "overdue" | "maintenance" | "draft" | "pending" | "terminated"> = {
  over_cap: "maintenance", overdue_3plus: "overdue", invite_stale_7d: "pending", no_property_7d: "draft", suspended: "terminated",
};
</script>

<template>
  <ul class="divide-y divide-line-passive">
    <li v-for="item in items" :key="`${item.kind}-${item.ownerId}`">
      <NuxtLink :to="item.link" class="block py-3 rounded-sm hover:bg-surface-hover focus-visible:shadow-focus transition -mx-2 px-2">
        <div class="flex items-center gap-2">
          <Pill :tone="tone[item.kind]">{{ t(`admin.dashboard.attention.kinds.${item.kind}`) }}</Pill>
          <span class="text-micro text-ink-faint tabular-nums">{{ item.meta }}</span>
        </div>
        <p class="mt-1 text-body font-medium text-ink">{{ item.ownerName }}</p>
      </NuxtLink>
    </li>
  </ul>
</template>
