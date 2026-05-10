<script setup lang="ts">
/**
 * Site-wide footer with designer credit + Axel Nova Ventures attribution.
 * Use this on any layout that needs a footer (marketing, auth, owner, tenant)
 * so credit + brand framing stay consistent across surfaces. Don't inline a
 * footer block in a layout — extend this component instead.
 *
 * Tone defaults to a dark surface (matches marketing/auth layout charcoal pane).
 * Pass `tone="light"` on light surfaces to flip the colour palette.
 */
type Tone = "dark" | "light";

const props = withDefaults(
  defineProps<{
    tone?: Tone;
  }>(),
  { tone: "dark" },
);

const year = new Date().getFullYear();

// Footer colours follow the brand accent (orange) on both light and dark
// surfaces so the credit line reads as Roofly-branded rather than chrome-grey.
const palette = computed(() =>
  props.tone === "light"
    ? {
        border: "rgba(196, 77, 38, 0.18)",
        text: "rgba(196, 77, 38, 0.75)",
        link: "#c44d26",
      }
    : {
        border: "rgba(231, 106, 63, 0.18)",
        text: "rgba(231, 106, 63, 0.75)",
        link: "#e76a3f",
      },
);
</script>

<template>
  <footer
    class="relative z-10 px-6 lg:px-12 py-8 mt-16 border-t"
    :style="{ borderColor: palette.border }"
  >
    <p
      class="text-micro text-center"
      :style="{ color: palette.text }"
    >
      © {{ year }} Roofly.my · Designed and developed with care and love by
      <a
        href="https://baihaqie.com"
        target="_blank"
        rel="noopener noreferrer"
        class="font-medium underline underline-offset-2 hover:opacity-80 transition-opacity"
        :style="{ color: palette.link }"
      >
        Qie
      </a>
      · One of the greatest projects of
      <a
        href="https://axelnovaventures.com"
        target="_blank"
        rel="noopener noreferrer"
        class="font-medium underline underline-offset-2 hover:opacity-80 transition-opacity"
        :style="{ color: palette.link }"
      >
        Axel Nova Ventures
      </a>
    </p>
  </footer>
</template>
