<script setup lang="ts">
import { computed, type Component } from "vue";
import { House } from "lucide-vue-next";

/**
 * The one header for every public surface (coming-soon, auth, legal,
 * onboarding, admin sign-in), so the Roofly wordmark sits in exactly the same
 * spot everywhere: px-6 → lg:px-12 from the left, py-5 from the top, in a
 * 36px (h-9) row — the height of the LangSwitcher / ThemeToggle buttons, so
 * rows with and without controls line up. Right-side controls go in the slot.
 * App shells (owner / tenant / admin) keep their sidebar wordmark.
 * UI-STANDARDS § 11.23.
 */
const props = withDefaults(
  defineProps<{
    /** Defaults to useEnv().publicHomePath — the public home, not "/" (which sends signed-in users to their dashboard). */
    to?: string;
    label?: string;
    icon?: Component;
    /** Inline colour for the icon on pinned-theme panes (charcoal marketing / admin). Default: text-accent. */
    iconColor?: string;
    /** Extra classes on the wordmark link, e.g. "md:hidden" on the auth form pane. */
    wordmarkClass?: string;
  }>(),
  { to: undefined, label: "Roofly.my", icon: () => House, iconColor: undefined, wordmarkClass: "" },
);

const { publicHomePath } = useEnv();
const href = computed(() => props.to ?? publicHomePath);
</script>

<template>
  <header class="relative z-10 flex items-center justify-between gap-2 px-6 py-5 lg:px-12">
    <NuxtLink
      :to="href"
      class="inline-flex h-9 items-center gap-2 rounded-sm text-card-title font-semibold tracking-tight focus-visible:shadow-focus"
      :class="wordmarkClass"
    >
      <component
        :is="icon"
        :size="22"
        :stroke-width="1.75"
        :class="iconColor ? '' : 'text-accent'"
        :style="iconColor ? { color: iconColor } : undefined"
      />
      <span>{{ label }}</span>
    </NuxtLink>
    <div v-if="$slots.default" class="ml-auto flex h-9 items-center gap-1">
      <slot />
    </div>
  </header>
</template>
