<script setup lang="ts">
import * as icons from "lucide-vue-next";

const props = withDefaults(
  defineProps<{ name: keyof typeof icons; size?: number; strokeWidth?: number }>(),
  { size: 18, strokeWidth: 1.5 },
);

const Component = computed(() => icons[props.name]);
</script>

<template>
  <component
    :is="Component"
    :size="size"
    :stroke-width="strokeWidth"
    aria-hidden="true"
  />
</template>
