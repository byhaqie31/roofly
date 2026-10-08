<script setup lang="ts">
import { computed } from "vue";
import Pill from "~/components/ui/Pill.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import { formatAdminDateTime } from "~/utils/adminDate";
import type { LeadEvent, TrackEvent } from "~/types/analytics";

const props = defineProps<{ events: LeadEvent[] }>();
const { t } = useI18n();

type Tone = "neutral" | "maintenance" | "draft" | "active";
const TONE: Record<TrackEvent, Tone> = {
  page_view: "neutral",
  demo_enter: "maintenance",
  demo_feedback_click: "maintenance",
  waitlist_signup: "draft",
  register: "active",
};

const PROP_KEYS = ["email", "role", "userId"] as const;

const fmt = formatAdminDateTime;

const rows = computed(() =>
  props.events.map((e) => ({
    e,
    line: PROP_KEYS.filter((key) => e.props[key] != null && e.props[key] !== "")
      .map((key) => `${key}: ${e.props[key]}`)
      .join(" · "),
  })),
);
</script>

<template>
  <EmptyState v-if="events.length === 0" icon="Activity" :title="t('admin.analytics.drawer.noEvents')" />
  <ul v-else class="divide-y divide-line-passive">
    <li v-for="row in rows" :key="row.e.id" class="py-3">
      <div class="flex flex-wrap items-center gap-2">
        <Pill :tone="TONE[row.e.event]">{{ t(`admin.analytics.events.${row.e.event}`) }}</Pill>
        <span class="text-micro text-ink-faint tabular-nums">{{ fmt(row.e.createdAt) }}</span>
      </div>
      <p class="mt-1 truncate text-body font-medium text-ink">{{ row.e.path ?? "—" }}</p>
      <p v-if="row.line" class="mt-1 text-micro text-ink-muted">{{ row.line }}</p>
    </li>
  </ul>
</template>
