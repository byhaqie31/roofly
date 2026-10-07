<script setup lang="ts">
import { Info } from "lucide-vue-next";

/**
 * Small floating "i" pill that tells testers they're on UAT, not production.
 * Click expands it in place to show the notice; it collapses back to the icon
 * after 5 seconds (or on a second click), so it never covers page content.
 *
 * Mounted once in app.vue behind useEnv().showEnvBanner (true only when
 * NUXT_PUBLIC_APP_ENV=uat). Positioned bottom-left so it never overlaps the
 * demo feedback button (bottom-right).
 */
const { t } = useI18n();
const { env } = useEnv();
const expanded = ref(false);

// Auto-collapse 5s after expanding; a manual collapse cancels the timer.
let collapseTimer: ReturnType<typeof setTimeout> | undefined;

function toggle() {
  clearTimeout(collapseTimer);
  expanded.value = !expanded.value;
  if (expanded.value) {
    collapseTimer = setTimeout(() => (expanded.value = false), 5000);
  }
}

onBeforeUnmount(() => clearTimeout(collapseTimer));
</script>

<template>
  <button
    type="button"
    :aria-expanded="expanded"
    :aria-label="`${env.toUpperCase()}: ${t('common.envBanner')}`"
    class="fixed bottom-4 left-4 z-50 inline-flex h-9 max-w-[calc(100vw-2rem)] items-center rounded-full bg-ink pl-[9px] pr-[9px] text-caption font-medium text-surface-page shadow-lg outline-none transition-colors hover:bg-ink-strong focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2"
    @click="toggle"
  >
    <Info :size="18" :stroke-width="1.5" class="shrink-0" aria-hidden="true" />
    <span
      class="grid transition-[grid-template-columns] duration-200 ease-out motion-reduce:transition-none"
      :class="expanded ? 'grid-cols-[1fr]' : 'grid-cols-[0fr]'"
    >
      <span class="overflow-hidden whitespace-nowrap">
        <span class="pl-2 pr-1">
          <span class="uppercase tracking-wide">{{ env }}</span>
          <span class="mx-1.5 opacity-50">·</span>
          <span>{{ t("common.envBanner") }}</span>
        </span>
      </span>
    </span>
  </button>
</template>
