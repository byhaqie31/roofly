<script setup lang="ts">
import { computed } from "vue";
import { inlineSegments } from "~/utils/legal";

// One line of legal copy with its `[label](href)` links turned into real links.
const props = defineProps<{ text: string }>();
const segments = computed(() => inlineSegments(props.text));
</script>

<template>
  <template v-for="(seg, i) in segments" :key="i">
    <NuxtLink
      v-if="seg.href"
      :to="seg.href"
      :external="!seg.href.startsWith('/')"
      class="text-ink underline underline-offset-2 hover:text-accent focus-visible:shadow-focus rounded-sm transition-colors"
    >{{ seg.text }}</NuxtLink>
    <template v-else>{{ seg.text }}</template>
  </template>
</template>
