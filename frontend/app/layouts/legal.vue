<script setup lang="ts">
import ThemeToggle from "~/components/topbar/ThemeToggle.vue";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";
import SiteFooter from "~/components/layout/SiteFooter.vue";
import SiteHeader from "~/components/layout/SiteHeader.vue";

// Public legal pages (/legal/*). Follows the visitor's theme (unlike the
// always-dark marketing layout) so long-form reading works in light and dark.
</script>

<template>
  <div class="min-h-dvh flex flex-col bg-surface-page text-ink">
    <SiteHeader class="border-b border-line-passive">
      <ThemeToggle />
      <LangSwitcher />
    </SiteHeader>

    <main class="flex-1">
      <slot />
    </main>

    <SiteFooter tone="theme" variant="full" />
  </div>
</template>
