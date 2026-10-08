<script setup lang="ts">
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";
import SiteFooter from "~/components/layout/SiteFooter.vue";
import SiteHeader from "~/components/layout/SiteHeader.vue";
</script>

<template>
  <!-- data-theme="light" pins the light token set on this subtree, same as
       layouts/auth.vue's form pane. Deliberate: the owner arrives here straight
       from the (always-light) signup form, and onboarding is still part of
       that first-run moment before the themed dashboard takes over — so the
       visual experience stays continuous rather than snapping to whatever
       theme they had set previously. -->
  <div data-theme="light" class="min-h-dvh flex flex-col bg-surface-page text-ink">
    <SiteHeader>
      <LangSwitcher />
    </SiteHeader>

    <main class="flex-1 flex items-center justify-center px-6 py-10 sm:py-16">
      <div class="w-full max-w-2xl">
        <slot />
      </div>
    </main>

    <SiteFooter tone="theme" variant="slim" />
  </div>
</template>
