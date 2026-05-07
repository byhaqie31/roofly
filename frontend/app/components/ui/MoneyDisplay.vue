<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    cents: number | null | undefined;
    emphasis?: boolean;
  }>(),
  { emphasis: false },
);

const { formatRM } = useMoney();
const formatted = computed(() => formatRM(props.cents));
</script>

<template>
  <span :class="['num', emphasis ? 'font-semibold text-ink' : 'text-ink-body']">
    {{ formatted }}
  </span>
</template>
