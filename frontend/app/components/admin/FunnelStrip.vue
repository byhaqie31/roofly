<script setup lang="ts">
import { computed } from "vue";
import Card from "~/components/ui/Card.vue";

export interface FunnelStep {
  key: string;
  label: string;
  count: number;
}

const props = defineProps<{ steps: FunnelStep[] }>();
const { t } = useI18n();

const rows = computed(() =>
  props.steps.map((step, i) => {
    const prev = i === 0 ? step.count : props.steps[i - 1]!.count;
    const pct = i === 0 ? 100 : prev > 0 ? Math.round((step.count / prev) * 100) : 0;
    return { ...step, pct };
  }),
);
</script>

<template>
  <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <Card v-for="row in rows" :key="row.key" padding="standard">
      <p class="text-caption text-ink-muted">{{ row.label }}</p>
      <p class="mt-2 text-display-sub font-semibold tracking-snug tabular-nums">{{ row.count }}</p>
      <p class="mt-1 text-micro text-ink-faint">{{ t("admin.analytics.funnel.pctOfPrevious", { pct: row.pct }) }}</p>
    </Card>
  </div>
</template>
