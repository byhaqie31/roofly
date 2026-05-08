<script setup lang="ts">
withDefaults(
  defineProps<{
    padding?: "compact" | "standard" | "loose";
    radius?: "md" | "lg" | "xl";
    tone?: "flat" | "raised";
  }>(),
  { padding: "standard", radius: "lg", tone: "raised" },
);
</script>

<template>
  <div
    :class="[
      'border border-line-passive',
      tone === 'raised' ? 'bg-surface-raised' : 'bg-surface-page',
      radius === 'md' ? 'rounded-md' : radius === 'xl' ? 'rounded-xl' : 'rounded-lg',
      padding === 'compact' ? 'p-4' : padding === 'loose' ? 'p-8' : 'p-6',
    ]"
  >
    <slot />
  </div>
</template>
