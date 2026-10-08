<script setup lang="ts">
import { ArrowDown, PlayCircle } from "lucide-vue-next";

const { t, tm, rt } = useI18n();
const { enter, slideshow, rotateWords, bob, wiggleOnHover } = useHeroMotion();

const emit = defineEmits<{
  "scroll-to-capture": [];
}>();

const onPrimaryCta = () => emit("scroll-to-capture");

// Brand words the accent line cycles through. words[0] is rendered on the
// server so first paint is never empty; GSAP takes over on the client.
const words = computed(() =>
  (tm("marketing.hero.headlineWords") as unknown[]).map((w) => rt(w as never)),
);

// Background slideshow — generated blue-hour property photos under
// public/marketing/hero/ (see UI-STANDARDS § 11.21 and scripts/hero/README.md).
// Array order is playback order; slides[0] is rendered visible server-side
// and fetched eagerly. To add a slide, pack the photo and append its name.
const slides = ["bungalow", "terrace", "condo", "semid", "apartment"].map(
  (name) => `/marketing/hero/${name}.webp`,
);

const stack = ref<HTMLElement | null>(null);
const accent = ref<HTMLElement | null>(null);
const stage = ref<HTMLElement | null>(null);
const badge = ref<HTMLElement | null>(null);
const demoCta = ref<HTMLElement | null>(null);

let stopRotate: (() => void) | null = null;
let stopSlides: (() => void) | null = null;
let bobTween: ReturnType<typeof bob> = null;
let stopWiggle: (() => void) | null = null;

onMounted(() => {
  if (stage.value) {
    enter(Array.from(stage.value.querySelectorAll<HTMLElement>("[data-enter]")));
  }
  if (stack.value) {
    stopSlides = slideshow(Array.from(stack.value.querySelectorAll<HTMLElement>("[data-slide]")));
  }
  if (badge.value) bobTween = bob(badge.value);
  if (demoCta.value && badge.value) stopWiggle = wiggleOnHover(demoCta.value, badge.value, bobTween);
  if (accent.value) stopRotate = rotateWords(accent.value, words.value);
});

// Restart the rotation in the new language when the locale flips.
watch(words, (next) => {
  stopRotate?.();
  if (accent.value) {
    accent.value.textContent = next[0] ?? "";
    stopRotate = rotateWords(accent.value, next);
  }
});

onBeforeUnmount(() => {
  stopRotate?.();
  stopSlides?.();
  bobTween?.kill();
  stopWiggle?.();
});
</script>

<template>
  <section
    ref="stage"
    class="relative flex flex-col items-center justify-center text-center px-6 lg:px-12 py-20 lg:py-28 min-h-[80vh] overflow-hidden"
  >
    <!-- Backdrop: crossfading slideshow of property photos with a slow
         Ken-Burns drift (useHeroMotion().slideshow). The first slide is
         visible server-side; the rest start hidden and are lazy-loaded.
         Dark gradient on top so the headline keeps AA contrast over photos.
         `isolate` gives the stack its own stacking context so the z-indexes
         the slideshow sets on the slides (and z-[3] on the gradient) can
         never climb above the hero copy, which is plain `relative`. -->
    <div ref="stack" class="absolute inset-0 pointer-events-none isolate" aria-hidden="true">
      <img
        v-for="(src, idx) in slides"
        :key="src"
        data-slide
        :src="src"
        alt=""
        width="1920"
        height="1080"
        :loading="idx === 0 ? 'eager' : 'lazy'"
        :fetchpriority="idx === 0 ? 'high' : 'low'"
        decoding="async"
        class="absolute inset-0 w-full h-full object-cover object-center will-change-transform"
        :class="idx === 0 ? 'opacity-100' : 'opacity-0 invisible'"
      />
      <div
        class="absolute inset-0 z-[3]"
        style="
          background:
            linear-gradient(180deg, #1c1a17 0%, rgba(28, 26, 23, 0.76) 35%, rgba(28, 26, 23, 0.64) 65%, #1c1a17 100%);
        "
      />
    </div>

    <div
      data-enter
      class="relative inline-flex items-center gap-2.5 px-4 py-2 rounded-pill mb-8 opacity-0"
      style="
        background: rgba(231, 106, 63, 0.12);
        box-shadow: inset 0 0 0 1px rgba(231, 106, 63, 0.35);
      "
    >
      <span class="relative inline-flex w-2.5 h-2.5">
        <span
          class="absolute inset-0 rounded-pill animate-ping"
          style="background-color: #e76a3f; opacity: 0.6"
        />
        <span
          class="relative inline-block w-2.5 h-2.5 rounded-pill"
          style="background-color: #e76a3f"
        />
      </span>
      <span
        class="text-caption font-semibold uppercase tracking-[0.18em]"
        style="color: #e76a3f"
      >
        {{ t("marketing.hero.eyebrow") }}
      </span>
    </div>

    <h1
      data-enter
      class="relative text-display-section md:text-display-hero font-semibold tracking-tight leading-[1.02] max-w-4xl opacity-0"
    >
      <span class="block" style="color: #f7f4ed">
        {{ t("marketing.hero.headlineLead") }}
      </span>
      <span class="block overflow-hidden">
        <span
          ref="accent"
          class="inline-block will-change-transform"
          style="color: #e76a3f"
          aria-live="off"
        >{{ words[0] }}</span>
      </span>
    </h1>

    <p
      data-enter
      class="relative mt-8 text-body-lg max-w-2xl opacity-0"
      style="color: rgba(247, 244, 237, 0.82)"
    >
      {{ t("marketing.hero.subhead") }}
    </p>

    <div
      data-enter
      class="relative mt-10 w-full max-w-md sm:max-w-none sm:w-auto flex flex-col sm:flex-row items-stretch sm:items-center gap-3 opacity-0"
    >
      <!-- Mobile: "Explore demo" first (DOM + tab order); from sm: the
           primary "Get notified" sits on the left again (UI-STANDARDS § 11.22a). -->
      <a
        ref="demoCta"
        href="https://demo.roofly.my"
        target="_blank"
        rel="noopener noreferrer"
        class="relative inline-flex items-center justify-center gap-2 px-6 py-3 rounded-pill text-body font-medium will-change-transform sm:order-2"
        style="
          background-color: #f7f4ed;
          color: #1c1a17;
          box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12);
        "
      >
        <PlayCircle :size="18" :stroke-width="1.75" />
        {{ t("marketing.hero.ctaSecondary") }}
        <!-- "Try me" badge: pinned to the top-right corner, gentle bob via GSAP -->
        <span
          ref="badge"
          class="absolute -top-3 -right-3 px-2.5 py-0.5 rounded-pill text-micro font-semibold uppercase tracking-[0.12em] rotate-6 pointer-events-none"
          style="background-color: #e76a3f; color: #1c1a17; box-shadow: 0 4px 12px -4px rgba(231, 106, 63, 0.6)"
        >
          {{ t("marketing.hero.ctaSecondaryBadge") }}
        </span>
      </a>

      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-pill text-body font-semibold sm:order-1 transition-all hover:scale-[1.02] active:scale-100"
        style="background-color: #e76a3f; color: #1c1a17"
        @click="onPrimaryCta"
      >
        {{ t("marketing.hero.ctaPrimary") }}
        <ArrowDown :size="18" :stroke-width="2" />
      </button>
    </div>
  </section>
</template>
