<script setup lang="ts">
import { Sparkles } from "lucide-vue-next";

/**
 * Topbar trigger to (re-)play the demo product tour. Mount only on demo
 * surfaces — gated by useEnv().isDemo at the mount site.
 */
const { t } = useI18n();
const { start } = useDemoTour();
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center gap-1.5 h-9 px-3 rounded-pill text-caption font-medium text-accent border border-accent/30 hover:bg-accent-soft focus-visible:shadow-focus transition"
    @click="start"
  >
    <Sparkles :size="14" :stroke-width="1.75" />
    <span>{{ t("demo.tour.ctaLabel") }}</span>
  </button>
</template>
