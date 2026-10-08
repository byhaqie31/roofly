<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
defineProps<{ label: string; value: number | string; help?: string }>();
</script>

<template>
  <Card padding="standard">
    <p class="text-caption text-ink-muted">{{ label }}</p>
    <p class="mt-2 text-display-sub font-semibold tracking-snug tabular-nums">{{ value }}</p>
    <p v-if="help" class="mt-1 text-micro text-ink-faint">{{ help }}</p>
  </Card>
</template>
