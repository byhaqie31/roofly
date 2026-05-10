<script setup lang="ts">
import { ArrowDown, PlayCircle } from "lucide-vue-next";

const { t } = useI18n();

const emit = defineEmits<{
  "scroll-to-capture": [];
}>();

const onPrimaryCta = () => emit("scroll-to-capture");
</script>

<template>
  <section
    class="relative flex flex-col items-center justify-center text-center px-6 lg:px-12 py-20 lg:py-28 min-h-[80vh]"
  >
    <div
      class="inline-flex items-center gap-2.5 px-4 py-2 rounded-pill mb-8"
      style="
        background: rgba(231, 106, 63, 0.12);
        box-shadow: inset 0 0 0 1px rgba(231, 106, 63, 0.35);
      "
    >
      <span
        class="relative inline-flex w-2.5 h-2.5"
      >
        <!-- Outer ping ring for active "live" feel -->
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
      class="text-display-section md:text-display-hero font-semibold tracking-tight leading-[1.02] max-w-4xl"
    >
      <span class="block" style="color: #f7f4ed">
        {{ t("marketing.hero.headlineLead") }}
      </span>
      <span class="block" style="color: #e76a3f">
        {{ t("marketing.hero.headlineAccent") }}
      </span>
    </h1>

    <p
      class="mt-8 text-body-lg max-w-2xl"
      style="color: rgba(247, 244, 237, 0.82)"
    >
      {{ t("marketing.hero.subhead") }}
    </p>

    <div class="mt-10 flex flex-col sm:flex-row items-center gap-3">
      <button
        type="button"
        class="inline-flex items-center gap-2 px-6 py-3 rounded-pill text-body font-semibold transition-all hover:scale-[1.02] active:scale-100"
        style="background-color: #e76a3f; color: #1c1a17"
        @click="onPrimaryCta"
      >
        {{ t("marketing.hero.ctaPrimary") }}
        <ArrowDown :size="18" :stroke-width="2" />
      </button>

      <a
        href="https://demo.roofly.my"
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-2 px-6 py-3 rounded-pill text-body font-medium transition-all hover:opacity-100"
        style="
          color: rgba(247, 244, 237, 0.85);
          box-shadow: inset 0 0 0 1px rgba(247, 244, 237, 0.2);
        "
      >
        <PlayCircle :size="18" :stroke-width="1.75" />
        {{ t("marketing.hero.ctaSecondary") }}
      </a>
    </div>
  </section>
</template>
