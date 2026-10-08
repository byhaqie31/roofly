<script setup lang="ts">
// Promo reel section on the coming-soon page. Playback (cut selection,
// in-view autoplay, missing-file detection) lives in usePromoVideo; a
// missing file hides the whole section.
const { t } = useI18n();
const video = ref<HTMLVideoElement | null>(null);
const { variant, src, poster, failed } = usePromoVideo(video);
</script>

<template>
  <section
    v-if="variant && !failed"
    class="px-6 lg:px-12 py-16 lg:py-24 max-w-6xl mx-auto"
  >
    <header class="max-w-2xl mb-10 lg:mb-12">
      <p
        class="text-micro font-medium uppercase tracking-[0.2em] mb-4"
        style="color: #e76a3f"
      >
        {{ t("marketing.promo.eyebrow") }}
      </p>
      <h2
        class="text-display-sub md:text-display-section font-semibold tracking-tight leading-[1.05]"
        style="color: #f7f4ed"
      >
        {{ t("marketing.promo.title") }}
      </h2>
    </header>

    <div
      class="rounded-lg overflow-hidden mx-auto"
      :class="variant === 'mobile' ? 'max-w-sm' : 'w-full'"
      style="
        background: rgba(247, 244, 237, 0.04);
        border: 1px solid rgba(247, 244, 237, 0.1);
      "
    >
      <video
        ref="video"
        :key="src"
        :src="src"
        :poster="poster"
        class="block w-full h-auto"
        :class="variant === 'mobile' ? 'max-h-[80vh] object-contain' : ''"
        :aria-label="t('marketing.promo.title')"
        muted
        loop
        playsinline
        controls
        preload="metadata"
        @error="failed = true"
      />
    </div>
  </section>
</template>
