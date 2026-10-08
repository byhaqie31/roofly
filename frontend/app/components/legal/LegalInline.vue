<script setup lang="ts">
import { computed } from "vue";
import { inlineSegments, scopeLegalHref } from "~/utils/legal";

// One line of legal copy with its `[label](href)` links turned into real links.
// `basePath` keeps links to other legal documents inside the app being read in.
const props = defineProps<{ text: string; basePath?: string }>();
const segments = computed(() =>
  inlineSegments(props.text).map((s) => (s.href ? { ...s, href: scopeLegalHref(s.href, props.basePath) } : s)),
);
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
