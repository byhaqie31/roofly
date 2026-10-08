<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from "vue";

interface AreaDatum {
  key: string;
  label: string;
  amount: number;     // sen
}

const { formatRM } = useMoney();

const props = withDefaults(
  defineProps<{
    data: AreaDatum[];
    height?: number;        // pixels
    highlightLast?: boolean;
    showAverageLine?: boolean;
    /** Value formatter — defaults to RM (sen). Admin charts pass a count/percent formatter. */
    format?: (n: number) => string;
    /** `area` for continuous series; `bars` for sparse integer counts (e.g. sign-ups per day). */
    variant?: "area" | "bars";
    /** Max x-axis labels to render. Series longer than this get evenly spaced ticks (first + last always shown). */
    maxTicks?: number;
  }>(),
  { height: 120, highlightLast: true, showAverageLine: true, variant: "area", maxTicks: 12 },
);

// Not a defineProps default on purpose: the SFC compiler hoists defineProps,
// so a default may not reference the locally destructured `formatRM`.
const format = (n: number) => (props.format ?? formatRM)(n);

const hoverIdx = ref<number | null>(null);

// Internal SVG units. preserveAspectRatio="none" lets it stretch to width;
// height is fixed via inline style on the wrapper.
const SVG_W = 600;
const PAD_TOP = 16;          // headroom for the highlighted-point value badge
const PAD_BOTTOM = 4;        // baseline breathing room

const SVG_H = computed(() => props.height);
const usableH = computed(() => SVG_H.value - PAD_TOP - PAD_BOTTOM);
const baseline = computed(() => SVG_H.value - PAD_BOTTOM);

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

const isBars = computed(() => props.variant === "bars");

// Area points sit on the edges (first at x=0, last at x=W); bars sit in column centres.
const points = computed<Point[]>(() => {
  const n = props.data.length;
  if (n === 0) return [];
  return props.data.map((d, i) => {
    const x = isBars.value
      ? ((i + 0.5) / n) * SVG_W
      : n === 1 ? SVG_W / 2 : (i / (n - 1)) * SVG_W;
    const y = PAD_TOP + (1 - d.amount / max.value) * usableH.value;
    return { x, y, amount: d.amount, label: d.label, key: d.key };
  });
});

// Bars: column width minus a 2px-ish surface gap, expressed in SVG units.
const barWidth = computed(() => {
  const n = Math.max(1, props.data.length);
  const col = SVG_W / n;
  return Math.max(1, col - Math.min(col * 0.35, 6));
});

const linePath = computed(() => {
  if (points.value.length === 0) return "";
  return points.value
    .map((p, i) => `${i === 0 ? "M" : "L"}${p.x.toFixed(2)},${p.y.toFixed(2)}`)
    .join(" ");
});

const areaPath = computed(() => {
  if (points.value.length === 0) return "";
  const first = points.value[0]!;
  const last = points.value[points.value.length - 1]!;
  return (
    linePath.value +
    ` L${last.x.toFixed(2)},${baseline.value}` +
    ` L${first.x.toFixed(2)},${baseline.value} Z`
  );
});

const avgY = computed(() => {
  if (max.value === 0) return baseline.value;
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

// Badge / tooltip anchoring: centred by default, but hug the edge near either end
// so the label never runs outside the chart box.
const anchorClass = (p: Point) => {
  const x = pctX(p);
  if (x < 10) return "translate-x-0";
  if (x > 90) return "-translate-x-full";
  return "-translate-x-1/2";
};

// X-axis ticks: all points when the series is short, otherwise evenly spaced
// with the first and last always present. Long daily series would otherwise
// print one illegible truncated label per column.
// Tick budget is also width-aware (~one label per 70px) so narrow cards don't crowd.
const wrapper = ref<HTMLElement | null>(null);
const widthPx = ref(0);
let ro: ResizeObserver | null = null;
onMounted(() => {
  if (!wrapper.value) return;
  widthPx.value = wrapper.value.clientWidth;
  if (typeof ResizeObserver !== "undefined") {
    ro = new ResizeObserver(([entry]) => { widthPx.value = entry?.contentRect.width ?? widthPx.value; });
    ro.observe(wrapper.value);
  }
});
onBeforeUnmount(() => ro?.disconnect());
const tickBudget = computed(() =>
  widthPx.value > 0 ? Math.max(2, Math.min(props.maxTicks, Math.floor(widthPx.value / 70))) : props.maxTicks,
);

const ticks = computed(() => {
  const n = props.data.length;
  if (n === 0) return [];
  if (n <= tickBudget.value) return points.value;
  const step = Math.ceil((n - 1) / Math.max(1, tickBudget.value - 1));
  const idx = new Set<number>();
  for (let i = 0; i < n; i += step) idx.add(i);
  // Drop a penultimate tick that would crowd the last one.
  const arr = [...idx].filter((i) => n - 1 - i >= step * 0.5);
  arr.push(n - 1);
  return arr.map((i) => points.value[i]!);
});
</script>

<template>
  <div v-if="data.length > 0" ref="wrapper" class="w-full">
    <div
      class="relative w-full"
      :style="{ height: `${height}px` }"
      role="img"
      :aria-label="`${isBars ? 'Bar' : 'Area'} chart, ${data.length} buckets, max ${format(max)}`"
      @mouseleave="hoverIdx = null"
    >
      <!-- The chart itself -->
      <svg
        :viewBox="`0 0 ${SVG_W} ${SVG_H}`"
        preserveAspectRatio="none"
        class="absolute inset-0 h-full w-full"
      >
        <!-- Baseline -->
        <line
          :x1="0"
          :x2="SVG_W"
          :y1="baseline"
          :y2="baseline"
          stroke="var(--border-passive)"
          stroke-width="1"
          vector-effect="non-scaling-stroke"
        />

        <!-- Average reference line -->
        <line
          v-if="showAverageLine && average > 0"
          :x1="0"
          :x2="SVG_W"
          :y1="avgY"
          :y2="avgY"
          stroke="var(--border-interactive)"
          stroke-dasharray="3 3"
          stroke-width="1"
          vector-effect="non-scaling-stroke"
        />

        <template v-if="isBars">
          <rect
            v-for="(p, idx) in points"
            :key="p.key"
            :x="p.x - barWidth / 2"
            :y="p.amount > 0 ? p.y : baseline - 1"
            :width="barWidth"
            :height="p.amount > 0 ? baseline - p.y : 1"
            fill="var(--text-primary)"
            :fill-opacity="p.amount === 0 ? 0.12 : hoverIdx === idx ? 1 : highlightLast && idx === lastIdx ? 1 : 0.55"
          />
        </template>

        <template v-else>
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

          <!-- Highlighted point (latest bucket) -->
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
        </template>

        <!-- Hover crosshair (bars) -->
        <line
          v-if="hoverPoint && isBars"
          :x1="hoverPoint.x"
          :x2="hoverPoint.x"
          :y1="PAD_TOP"
          :y2="baseline"
          stroke="var(--border-interactive)"
          stroke-width="1"
          vector-effect="non-scaling-stroke"
        />
      </svg>

      <!-- Avg label, anchored to the left end of the avg line so it never collides with the latest-value badge on the right -->
      <span
        v-if="showAverageLine && average > 0"
        class="pointer-events-none absolute left-0 -translate-y-full bg-surface-raised pr-1.5 text-micro text-ink-muted tabular-nums"
        :style="{ top: `${(avgY / SVG_H) * 100}%` }"
      >
        {{ $t("owner.dashboard.incomeChart.avgLabel") }} {{ format(Math.round(average)) }}
      </span>

      <!-- Latest-bucket value badge (hidden while hovering) -->
      <span
        v-if="lastPoint && lastPoint.amount > 0 && hoverIdx === null"
        class="pointer-events-none absolute -translate-y-[140%] whitespace-nowrap rounded-xs bg-ink px-1.5 py-0.5 text-micro font-medium text-surface-raised tabular-nums"
        :class="anchorClass(lastPoint)"
        :style="{ left: `${pctX(lastPoint)}%`, top: `${pctY(lastPoint)}%` }"
      >
        {{ format(lastPoint.amount) }}
      </span>

      <!-- Hover tooltip -->
      <span
        v-if="hoverPoint"
        class="pointer-events-none absolute z-10 -translate-y-[140%] whitespace-nowrap rounded-xs border border-line-passive bg-surface-raised px-2 py-1 text-micro text-ink shadow-modal tabular-nums"
        :class="anchorClass(hoverPoint)"
        :style="{ left: `${pctX(hoverPoint)}%`, top: `${pctY(hoverPoint)}%` }"
      >
        {{ hoverPoint.label }} · {{ format(hoverPoint.amount) }}
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

    <!-- X-axis: one label per point for short series, sparse evenly spaced ticks for long ones -->
    <div v-if="data.length <= tickBudget && !isBars" class="mt-2 flex gap-px text-micro text-ink-faint">
      <span
        v-for="d in data"
        :key="`l-${d.key}`"
        class="flex-1 truncate text-center"
        :style="{ width: `${colWidthPct}%` }"
      >
        {{ d.label }}
      </span>
    </div>
    <div v-else class="relative mt-2 h-4 text-micro text-ink-faint">
      <span
        v-for="p in ticks"
        :key="`t-${p.key}`"
        class="absolute top-0 whitespace-nowrap tabular-nums"
        :class="anchorClass(p)"
        :style="{ left: `${pctX(p)}%` }"
      >
        {{ p.label }}
      </span>
    </div>
  </div>
</template>
