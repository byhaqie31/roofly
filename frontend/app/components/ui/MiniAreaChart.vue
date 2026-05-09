<script setup lang="ts">
import { computed, ref } from "vue";

interface AreaDatum {
  key: string;
  label: string;
  amount: number;     // sen
}

const props = withDefaults(
  defineProps<{
    data: AreaDatum[];
    height?: number;        // pixels
    highlightLast?: boolean;
    showAverageLine?: boolean;
  }>(),
  { height: 120, highlightLast: true, showAverageLine: true },
);

const { formatRM } = useMoney();
const hoverIdx = ref<number | null>(null);

// Internal SVG units. preserveAspectRatio="none" lets it stretch to width;
// height is fixed via inline style on the wrapper.
const SVG_W = 600;
const PAD_TOP = 16;          // headroom for the highlighted-point value badge
const PAD_BOTTOM = 4;        // baseline breathing room

const SVG_H = computed(() => props.height);
const usableH = computed(() => SVG_H.value - PAD_TOP - PAD_BOTTOM);

const max = computed(() =>
  Math.max(1, ...props.data.map((d) => d.amount)),
);

const average = computed(() => {
  if (props.data.length === 0) return 0;
  return props.data.reduce((s, d) => s + d.amount, 0) / props.data.length;
});

const lastIdx = computed(() => props.data.length - 1);

interface Point {
  x: number;
  y: number;
  amount: number;
  label: string;
  key: string;
}

const points = computed<Point[]>(() => {
  const n = props.data.length;
  if (n === 0) return [];
  return props.data.map((d, i) => {
    const x = n === 1 ? SVG_W / 2 : (i / (n - 1)) * SVG_W;
    const y = PAD_TOP + (1 - d.amount / max.value) * usableH.value;
    return { x, y, amount: d.amount, label: d.label, key: d.key };
  });
});

const linePath = computed(() => {
  if (points.value.length === 0) return "";
  return points.value
    .map((p, i) => `${i === 0 ? "M" : "L"}${p.x.toFixed(2)},${p.y.toFixed(2)}`)
    .join(" ");
});

const areaPath = computed(() => {
  if (points.value.length === 0) return "";
  const baseline = SVG_H.value - PAD_BOTTOM;
  const first = points.value[0]!;
  const last = points.value[points.value.length - 1]!;
  return (
    linePath.value +
    ` L${last.x.toFixed(2)},${baseline}` +
    ` L${first.x.toFixed(2)},${baseline} Z`
  );
});

const avgY = computed(() => {
  if (max.value === 0) return SVG_H.value - PAD_BOTTOM;
  return PAD_TOP + (1 - average.value / max.value) * usableH.value;
});

// Per-column hit areas for hover/tap detection. Column width as % of total.
const colWidthPct = computed(() => 100 / Math.max(1, props.data.length));

const hoverPoint = computed(() =>
  hoverIdx.value !== null ? points.value[hoverIdx.value] : null,
);
const lastPoint = computed(() =>
  props.highlightLast && lastIdx.value >= 0 ? points.value[lastIdx.value] : null,
);

// Tooltip / badge x position as a percentage of width — picked so we can
// position the HTML element via `left: X%` and translate-x(-50%) to center.
const pctX = (p: Point) => (p.x / SVG_W) * 100;
const pctY = (p: Point) => (p.y / SVG_H.value) * 100;
</script>

<template>
  <div v-if="data.length > 0" class="w-full">
    <div
      class="relative w-full"
      :style="{ height: `${height}px` }"
      role="img"
      :aria-label="`Area chart, ${data.length} buckets, max ${formatRM(max)}`"
      @mouseleave="hoverIdx = null"
    >
      <!-- The chart itself -->
      <svg
        :viewBox="`0 0 ${SVG_W} ${SVG_H}`"
        preserveAspectRatio="none"
        class="absolute inset-0 h-full w-full"
      >
        <!-- Average reference line -->
        <line
          v-if="showAverageLine && average > 0"
          :x1="0"
          :x2="SVG_W"
          :y1="avgY"
          :y2="avgY"
          stroke="var(--border-passive)"
          stroke-dasharray="3 3"
          stroke-width="1"
          vector-effect="non-scaling-stroke"
        />

        <!-- Area fill -->
        <path
          :d="areaPath"
          fill="var(--text-primary)"
          fill-opacity="0.08"
        />

        <!-- Line -->
        <path
          :d="linePath"
          fill="none"
          stroke="var(--text-primary)"
          stroke-width="1.5"
          stroke-linejoin="round"
          stroke-linecap="round"
          vector-effect="non-scaling-stroke"
        />

        <!-- Highlighted point (current month) -->
        <circle
          v-if="lastPoint && lastPoint.amount > 0"
          :cx="lastPoint.x"
          :cy="lastPoint.y"
          r="4"
          fill="var(--text-primary)"
          stroke="var(--surface-raised)"
          stroke-width="2"
          vector-effect="non-scaling-stroke"
        />

        <!-- Hover point ring -->
        <circle
          v-if="hoverPoint"
          :cx="hoverPoint.x"
          :cy="hoverPoint.y"
          r="4"
          fill="var(--surface-raised)"
          stroke="var(--text-primary)"
          stroke-width="1.5"
          vector-effect="non-scaling-stroke"
        />
      </svg>

      <!-- Avg label, anchored top-right of the avg line -->
      <span
        v-if="showAverageLine && average > 0"
        class="pointer-events-none absolute right-0 -translate-y-full bg-surface-page px-1 text-micro text-ink-faint"
        :style="{ top: `${(avgY / SVG_H) * 100}%` }"
      >
        {{ $t("owner.dashboard.incomeChart.avgLabel") }}
      </span>

      <!-- Current-month value badge (hidden while hovering) -->
      <span
        v-if="lastPoint && lastPoint.amount > 0 && hoverIdx === null"
        class="pointer-events-none absolute -translate-x-1/2 -translate-y-[140%] whitespace-nowrap rounded-xs bg-ink px-1.5 py-0.5 text-micro font-medium text-surface-raised tabular-nums"
        :style="{ left: `${pctX(lastPoint)}%`, top: `${pctY(lastPoint)}%` }"
      >
        {{ formatRM(lastPoint.amount) }}
      </span>

      <!-- Hover tooltip -->
      <span
        v-if="hoverPoint"
        class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-[140%] whitespace-nowrap rounded-xs border border-line-passive bg-surface-raised px-2 py-1 text-micro text-ink shadow-modal tabular-nums"
        :style="{ left: `${pctX(hoverPoint)}%`, top: `${pctY(hoverPoint)}%` }"
      >
        {{ hoverPoint.label }} · {{ formatRM(hoverPoint.amount) }}
      </span>

      <!-- Hover hit columns (transparent) -->
      <div class="absolute inset-0 flex">
        <div
          v-for="(d, idx) in data"
          :key="d.key"
          class="flex-1 cursor-default"
          :style="{ width: `${colWidthPct}%` }"
          @mouseenter="hoverIdx = idx"
          @touchstart="hoverIdx = idx"
        />
      </div>
    </div>

    <div class="mt-2 flex gap-px text-micro text-ink-faint">
      <span
        v-for="d in data"
        :key="`l-${d.key}`"
        class="flex-1 truncate text-center"
        :style="{ width: `${colWidthPct}%` }"
      >
        {{ d.label }}
      </span>
    </div>
  </div>
</template>
