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

const rows = computed(() => {
  const top = props.steps[0]?.count ?? 0;
  return props.steps.map((step, i) => {
    const prev = i === 0 ? step.count : props.steps[i - 1]!.count;
    const pctOfPrev = i === 0 ? 100 : prev > 0 ? Math.round((step.count / prev) * 100) : 0;
    const share = top > 0 ? (step.count / top) * 100 : 0;
    return { ...step, pctOfPrev, share };
  });
});
</script>

<template>
  <Card padding="loose">
    <ol class="grid grid-cols-2 gap-x-6 gap-y-8 lg:grid-cols-4">
      <li v-for="(row, i) in rows" :key="row.key" class="min-w-0">
        <p class="text-caption text-ink-muted">{{ row.label }}</p>
        <p class="mt-1 text-display-sub font-semibold tracking-snug tabular-nums">{{ row.count }}</p>
        <!-- Share of the top step, so the bars shrink left-to-right like a real funnel -->
        <div class="mt-3 h-2 w-full overflow-hidden rounded-pill bg-line-passive" aria-hidden="true">
          <div
            class="h-full rounded-pill bg-ink transition-[width] duration-300 ease-out motion-reduce:transition-none"
            :style="{ width: `${row.count > 0 ? Math.max(row.share, 1.5) : 0}%` }"
          />
        </div>
        <p class="mt-2 text-micro tabular-nums text-ink-muted">
          <template v-if="i === 0">{{ t("admin.analytics.funnel.top") }}</template>
          <template v-else>{{ t("admin.analytics.funnel.pctOfPrevious", { pct: row.pctOfPrev }) }}</template>
        </p>
      </li>
    </ol>
  </Card>
</template>
