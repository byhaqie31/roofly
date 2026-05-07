<script setup lang="ts">
import { computed } from "vue";

type Tone =
  | "vacant" | "occupied" | "maintenance"
  | "paid" | "pending" | "overdue" | "cancelled"
  | "draft" | "active" | "expired" | "terminated"
  | "low" | "medium" | "high"
  | "neutral";

const props = withDefaults(defineProps<{ tone: Tone }>(), { tone: "neutral" });

const toneClass = computed(() => {
  const map: Record<Tone, string> = {
    vacant: "bg-status-vacant-soft text-status-vacant",
    occupied: "bg-status-occupied-soft text-status-occupied",
    maintenance: "bg-status-maintenance-soft text-status-maintenance",
    paid: "bg-status-paid-soft text-status-paid",
    pending: "bg-status-pending-soft text-status-pending",
    overdue: "bg-status-overdue-soft text-status-overdue",
    cancelled: "bg-status-cancelled-soft text-status-cancelled",
    draft: "bg-status-draft-soft text-status-draft",
    active: "bg-status-active-soft text-status-active",
    expired: "bg-status-expired-soft text-status-expired",
    terminated: "bg-status-terminated-soft text-status-terminated",
    low: "bg-priority-low-soft text-priority-low",
    medium: "bg-priority-medium-soft text-priority-medium",
    high: "bg-priority-high-soft text-priority-high",
    neutral: "bg-[rgba(28,28,28,0.04)] text-ink-muted",
  };
  return map[props.tone];
});
</script>

<template>
  <span
    :class="[
      'inline-flex items-center rounded-pill px-2.5 py-1 text-micro font-medium',
      toneClass,
    ]"
  >
    <slot />
  </span>
</template>
