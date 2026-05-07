<script setup lang="ts">
import { computed } from "vue";

type Variant = "primary" | "ghost" | "cream" | "accent";
type Size = "sm" | "md" | "lg";

const props = withDefaults(
  defineProps<{
    variant?: Variant;
    size?: Size;
    type?: "button" | "submit" | "reset";
    disabled?: boolean;
    loading?: boolean;
    block?: boolean;
  }>(),
  {
    variant: "primary",
    size: "md",
    type: "button",
    disabled: false,
    loading: false,
    block: false,
  },
);

const variantClass = computed(() => {
  switch (props.variant) {
    case "primary":
      return "bg-surface-dark text-surface-ondark shadow-btn-inset hover:opacity-95 active:opacity-80";
    case "accent":
      return "bg-accent text-surface-ondark shadow-btn-inset hover:bg-accent-hover active:opacity-90";
    case "ghost":
      return "bg-transparent text-ink border border-line-interactive hover:bg-[rgba(28,28,28,0.04)] active:opacity-80";
    case "cream":
      return "bg-surface-page text-ink hover:bg-[rgba(28,28,28,0.04)] active:opacity-80";
  }
  return "";
});

const sizeClass = computed(() => {
  switch (props.size) {
    case "sm":
      return "px-3 py-1.5 text-caption";
    case "lg":
      return "px-6 py-3 text-body";
    default:
      return "px-4 py-2 text-body";
  }
});
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="[
      'inline-flex items-center justify-center gap-2 rounded-sm font-normal transition focus-visible:shadow-focus disabled:opacity-40 disabled:cursor-not-allowed',
      block ? 'w-full' : '',
      sizeClass,
      variantClass,
    ]"
  >
    <span
      v-if="loading"
      class="inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"
    />
    <slot />
  </button>
</template>
