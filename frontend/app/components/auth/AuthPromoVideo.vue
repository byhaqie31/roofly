<script setup lang="ts">
import { Volume2, VolumeX } from "lucide-vue-next";

// The promo reel as the centrepiece of the auth layout's charcoal pane. Always
// the landscape cut, and only loaded from md up (the pane is hidden below), so
// phones never download it. The frame + poster render on the server so the pane
// doesn't jump when the video mounts. Emits `unavailable` if the file is missing
// so the layout can fall back to its old headline + USP deck.
const emit = defineEmits<{ unavailable: [] }>();
const { t } = useI18n();

const video = ref<HTMLVideoElement | null>(null);
const { src, poster, failed } = usePromoVideo(video, { fixed: "web", when: "(min-width: 768px)" });
watch(failed, (f) => { if (f) emit("unavailable"); });

// Ambient by default; the reel has a voiceover, so offer sound on demand.
const muted = ref(true);
const toggleSound = () => {
  const el = video.value;
  if (!el) return;
  muted.value = !muted.value;
  el.muted = muted.value;
  if (!muted.value) el.play().catch(() => {});
};
</script>

<template>
  <div class="relative text-center">
    <!-- Same line as the coming-soon hero, so the brand promise reads the same everywhere. -->
    <h2
      class="mb-8 text-[1.75rem] lg:text-[2rem] xl:text-[2.5rem] 2xl:text-display-section font-semibold tracking-tight leading-[1.1] text-balance"
      style="color: #f7f4ed"
    >
      {{ t("marketing.hero.headlineLead") }}
      <span style="color: #e76a3f">{{ t("marketing.hero.headlineWords.0") }}</span>
    </h2>

    <!-- The title may use the pane's full width; the frame caps at the stat cards' width. -->
    <div class="relative max-w-3xl mx-auto">
      <!-- Warm glow behind the frame -->
      <div
        class="absolute -inset-8 pointer-events-none"
        aria-hidden="true"
        style="background: radial-gradient(ellipse 70% 60% at 50% 55%, rgba(221, 112, 71, 0.28), transparent 70%); filter: blur(24px)"
      />

      <div
        class="relative aspect-video rounded-xl overflow-hidden bg-cover bg-center"
        :style="{
          backgroundImage: `url(${poster})`,
          boxShadow: '0 0 0 1px rgba(247, 244, 237, 0.12), 0 30px 80px -20px rgba(0, 0, 0, 0.7)',
        }"
      >
        <video
          v-if="src"
          ref="video"
          :src="src"
          :poster="poster"
          class="absolute inset-0 w-full h-full object-cover"
          :aria-label="t('marketing.promo.title')"
          muted
          loop
          playsinline
          preload="metadata"
          @error="failed = true"
        />

        <button
          v-if="src"
          type="button"
          class="absolute bottom-3 right-3 inline-flex items-center justify-center w-9 h-9 rounded-pill transition-colors hover:bg-black/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#e76a3f]"
          style="background: rgba(28, 26, 23, 0.6); color: #f7f4ed; backdrop-filter: blur(6px)"
          :aria-label="muted ? t('marketing.promo.unmute') : t('marketing.promo.mute')"
          :aria-pressed="!muted"
          @click="toggleSound"
        >
          <VolumeX v-if="muted" :size="16" :stroke-width="1.75" />
          <Volume2 v-else :size="16" :stroke-width="1.75" />
        </button>
      </div>
    </div>
  </div>
</template>
