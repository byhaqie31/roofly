<script setup lang="ts">
import * as icons from "lucide-vue-next";
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    icon?: keyof typeof icons;
    title: string;
    description?: string;
  }>(),
  { icon: "Inbox" },
);

const IconComp = computed(() => icons[props.icon]);
</script>

<template>
  <div class="flex flex-col items-center justify-center text-center py-16 px-6">
    <component
      :is="IconComp"
      :size="64"
      :stroke-width="1.5"
      class="text-ink-faint"
      aria-hidden="true"
    />
    <h3 class="mt-6 text-card-title font-semibold text-ink">{{ title }}</h3>
    <p
      v-if="description"
      class="mt-2 max-w-md text-caption text-ink-muted"
    >
      {{ description }}
    </p>
    <div v-if="$slots.action" class="mt-6">
      <slot name="action" />
    </div>
  </div>
</template>
