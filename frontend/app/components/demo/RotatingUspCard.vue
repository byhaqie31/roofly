<script setup lang="ts">
import {
  TrendingUp,
  Smartphone,
  MessageCircle,
  KanbanSquare,
  Languages,
  FileSignature,
  BarChart3,
  MapPin,
  Users,
  type LucideIcon,
} from "lucide-vue-next";

interface UspItem {
  icon: LucideIcon;
  title: string;
  subtitle: string;
}

// 9 USPs, paginated 3 per page = 3 pages of rotation.
const usps: UspItem[] = [
  {
    icon: TrendingUp,
    title: "Rent collection on autopilot",
    subtitle: "FPX integration via Billplz — tenants pay, you track.",
  },
  {
    icon: Smartphone,
    title: "Tenants pay in two taps",
    subtitle: "Mobile-first checkout, no app to install.",
  },
  {
    icon: MessageCircle,
    title: "WhatsApp reminders that work",
    subtitle: "Late notices land where Malaysians actually read.",
  },
  {
    icon: KanbanSquare,
    title: "Maintenance, Kanban-style",
    subtitle: "Tickets flow from new → in-progress → resolved.",
  },
  {
    icon: Languages,
    title: "Bilingual end-to-end",
    subtitle: "Bahasa Melayu and English, switchable per user.",
  },
  {
    icon: FileSignature,
    title: "Signed agreements in seconds",
    subtitle: "Generate, send, sign — no more Word templates.",
  },
  {
    icon: BarChart3,
    title: "Reports your accountant loves",
    subtitle: "Income, RPGT, deductions — exportable CSV/PDF.",
  },
  {
    icon: MapPin,
    title: "Built for Malaysian landlords",
    subtitle: "FPX, RPGT, BM, RM — not a US tool with a KL skin.",
  },
  {
    icon: Users,
    title: "Co-owner splits made simple",
    subtitle: "Multiple owners on one property? Track shares automatically.",
  },
];

const PAGE_SIZE = 3;
const ROTATION_MS = 7000;
const pageCount = Math.ceil(usps.length / PAGE_SIZE);

const pageIndex = ref(0);
const isPaused = ref(false);

let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  timer = setInterval(() => {
    if (isPaused.value) return;
    pageIndex.value = (pageIndex.value + 1) % pageCount;
  }, ROTATION_MS);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});

const visibleUsps = computed(() =>
  usps.slice(pageIndex.value * PAGE_SIZE, pageIndex.value * PAGE_SIZE + PAGE_SIZE),
);
</script>

<template>
  <div
    class="relative"
    @mouseenter="isPaused = true"
    @mouseleave="isPaused = false"
  >
    <!-- 3 USP cards in a row swapping together as a page every 7s. -->
    <Transition
      mode="out-in"
      enter-active-class="transition-opacity duration-500"
      leave-active-class="transition-opacity duration-300"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div :key="pageIndex" class="grid grid-cols-3 gap-3">
        <article
          v-for="usp in visibleUsps"
          :key="usp.title"
          class="relative rounded-lg p-4 overflow-hidden"
          style="
            background: rgba(247, 244, 237, 0.04);
            border: 1px solid rgba(247, 244, 237, 0.1);
            box-shadow: 0 1px 0 rgba(247, 244, 237, 0.04) inset;
          "
        >
          <!-- Watermark — large, faded, partially clipped off the right edge.
               Rendered first so text content stacks above it. -->
          <component
            :is="usp.icon"
            class="absolute -right-3 top-1/2 -translate-y-1/2 pointer-events-none"
            :size="96"
            :stroke-width="0.6"
            style="color: #e76a3f; opacity: 0.1"
            aria-hidden="true"
          />

          <!-- Content stacked vertically — icon pill → title → subtitle.
               relative wrapper keeps it visually above the absolute watermark. -->
          <div class="relative">
            <span
              class="inline-flex items-center justify-center w-9 h-9 rounded-pill mb-3"
              style="
                background: rgba(231, 106, 63, 0.16);
                color: #e76a3f;
                box-shadow: inset 0 0 0 1px rgba(231, 106, 63, 0.2);
              "
            >
              <component :is="usp.icon" :size="16" :stroke-width="1.75" />
            </span>
            <h3
              class="text-caption font-semibold leading-snug"
              style="color: #f7f4ed"
            >
              {{ usp.title }}
            </h3>
            <p
              class="mt-1 text-micro leading-snug"
              style="color: rgba(247, 244, 237, 0.7)"
            >
              {{ usp.subtitle }}
            </p>
          </div>
        </article>
      </div>
    </Transition>

    <!-- Page dots -->
    <div
      class="mt-5 flex justify-center gap-2"
      role="tablist"
      aria-label="Value proposition pages"
    >
      <button
        v-for="(_, i) in pageCount"
        :key="i"
        type="button"
        role="tab"
        :aria-label="`Page ${i + 1} of ${pageCount}`"
        :aria-selected="i === pageIndex"
        class="h-1 rounded-pill transition-all duration-300"
        :class="i === pageIndex ? 'w-8' : 'w-2 hover:w-4'"
        :style="{
          backgroundColor:
            i === pageIndex ? '#e76a3f' : 'rgba(247, 244, 237, 0.25)',
        }"
        @click="pageIndex = i"
      />
    </div>
  </div>
</template>
