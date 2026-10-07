<script setup lang="ts">
// Promo reel for the coming-soon page. Files live in public/marketing/promo/
// (see the README there): a landscape cut for md+ and a portrait cut below.
// The source is picked client-side via matchMedia so each device downloads
// only its own file; a missing file hides the whole section.
const MD_QUERY = "(min-width: 768px)";
const BASE = "/marketing/promo";

const { t } = useI18n();
const variant = ref<"web" | "mobile" | null>(null);
const failed = ref(false);
const video = ref<HTMLVideoElement | null>(null);

const src = computed(() => (variant.value ? `${BASE}/promo-${variant.value}.mp4` : ""));
const poster = computed(() => (variant.value ? `${BASE}/promo-${variant.value}-poster.webp` : undefined));

let mql: MediaQueryList | null = null;
let observer: IntersectionObserver | null = null;
const pickVariant = () => {
  variant.value = mql?.matches ? "web" : "mobile";
  failed.value = false;
};

onMounted(() => {
  mql = window.matchMedia(MD_QUERY);
  pickVariant();
  mql.addEventListener("change", pickVariant);
});

// Autoplay (muted) only while on screen, and never for reduced-motion users.
watch(video, (el) => {
  observer?.disconnect();
  if (!el || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry?.isIntersecting) el.play().catch(() => {});
      else el.pause();
    },
    { threshold: 0.4 },
  );
  observer.observe(el);
});

onBeforeUnmount(() => {
  mql?.removeEventListener("change", pickVariant);
  observer?.disconnect();
});
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
