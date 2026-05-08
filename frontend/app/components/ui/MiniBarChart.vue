<script setup lang="ts">
import { computed } from "vue";

interface BarDatum {
  key: string;
  label: string;
  amount: number;     // sen
}

const props = withDefaults(
  defineProps<{
    data: BarDatum[];
    height?: number;        // pixels
    highlightLast?: boolean;
  }>(),
  { height: 96, highlightLast: true },
);

const { formatRM } = useMoney();

const max = computed(() =>
  Math.max(1, ...props.data.map((d) => d.amount)),
);

const barWidthPct = computed(() =>
  props.data.length === 0 ? 100 : 100 / props.data.length,
);

const heightFor = (amount: number) => {
  if (amount <= 0) return 1; // 1px baseline so empty months still register
  return Math.max(2, Math.round((amount / max.value) * (props.height - 16)));
};
</script>

<template>
  <div v-if="data.length > 0" class="w-full">
    <div
      class="flex items-end gap-px"
      :style="{ height: `${height}px` }"
      role="img"
      :aria-label="`Bar chart, ${data.length} buckets, max ${formatRM(max)}`"
    >
      <div
        v-for="(d, idx) in data"
        :key="d.key"
        class="flex h-full flex-1 flex-col items-center justify-end"
        :style="{ width: `${barWidthPct}%` }"
        :title="`${d.label}: ${formatRM(d.amount)}`"
      >
        <div
          class="w-full rounded-t-xs transition"
          :class="[
            highlightLast && idx === data.length - 1
              ? 'bg-ink'
              : 'bg-ink-muted/40 group-hover:bg-ink-muted/60',
            d.amount === 0 ? 'bg-line-passive' : '',
          ]"
          :style="{ height: `${heightFor(d.amount)}px` }"
        />
      </div>
    </div>
    <div class="mt-2 flex gap-px text-micro text-ink-faint">
      <span
        v-for="d in data"
        :key="`l-${d.key}`"
        class="flex-1 text-center"
        :style="{ width: `${barWidthPct}%` }"
      >
        {{ d.label }}
      </span>
    </div>
  </div>
</template>
