<script setup lang="ts">
/**
 * Small fixed badge that tells testers they're on UAT, not production.
 *
 * Mounted once in app.vue behind useEnv().showEnvBanner (true only when
 * NUXT_PUBLIC_APP_ENV=uat). Positioned bottom-left and pointer-events-none so
 * it never overlaps the demo feedback button (bottom-right) or steals clicks
 * from mobile nav drawers.
 */
const { t } = useI18n();
const { env } = useEnv();
</script>

<template>
  <div
    class="pointer-events-none fixed bottom-4 left-4 z-50 select-none rounded-full bg-ink px-3 py-1.5 text-caption font-medium text-surface-page shadow-lg"
    role="status"
    aria-live="off"
  >
    <span class="uppercase tracking-wide">{{ env }}</span>
    <span class="mx-1.5 opacity-50">·</span>
    <span>{{ t("common.envBanner") }}</span>
  </div>
</template>
