<script setup lang="ts">
withDefaults(
  defineProps<{
    padding?: "compact" | "standard" | "loose";
    radius?: "md" | "lg" | "xl";
  }>(),
  { padding: "standard", radius: "lg" },
);
</script>

<template>
  <div
    :class="[
      'bg-surface-page border border-line-passive',
      radius === 'md' ? 'rounded-md' : radius === 'xl' ? 'rounded-xl' : 'rounded-lg',
      padding === 'compact' ? 'p-4' : padding === 'loose' ? 'p-8' : 'p-6',
    ]"
  >
    <slot />
  </div>
</template>
