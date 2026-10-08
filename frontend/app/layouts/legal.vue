<script setup lang="ts">
import { House } from "lucide-vue-next";
import ThemeToggle from "~/components/topbar/ThemeToggle.vue";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";
import SiteFooter from "~/components/layout/SiteFooter.vue";

// Public legal pages (/legal/*). Follows the visitor's theme (unlike the
// always-dark marketing layout) so long-form reading works in light and dark.
</script>

<template>
  <div class="min-h-dvh flex flex-col bg-surface-page text-ink">
    <header class="flex items-center justify-between gap-2 border-b border-line-passive px-4 py-3 sm:px-6">
      <NuxtLink
        to="/"
        class="inline-flex items-center gap-2 rounded-sm text-card-title font-semibold tracking-tight focus-visible:shadow-focus"
      >
        <House :size="22" :stroke-width="1.75" class="text-accent" />
        <span>Roofly.my</span>
      </NuxtLink>
      <div class="flex items-center gap-1">
        <ThemeToggle />
        <LangSwitcher />
      </div>
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <SiteFooter tone="theme" variant="full" />
  </div>
</template>
