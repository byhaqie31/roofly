<script setup lang="ts">
import { House } from "lucide-vue-next";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";

const { t } = useI18n();
</script>

<template>
  <!-- data-theme="light" pins the light token set on this subtree, same as
       layouts/auth.vue's form pane. Deliberate: the owner arrives here straight
       from the (always-light) signup form, and onboarding is still part of
       that first-run moment before the themed dashboard takes over — so the
       visual experience stays continuous rather than snapping to whatever
       theme they had set previously. -->
  <div data-theme="light" class="min-h-dvh flex flex-col bg-surface-page text-ink">
    <header class="flex items-center justify-between px-6 py-4">
      <NuxtLink
        to="/"
        class="inline-flex items-center gap-2 text-card-title font-semibold tracking-tight"
      >
        <House :size="22" :stroke-width="1.75" class="text-accent" />
        <span>Roofly.my</span>
      </NuxtLink>
      <div class="flex items-center gap-1">
        <LangSwitcher />
      </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-6 py-10 sm:py-16">
      <div class="w-full max-w-2xl">
        <slot />
      </div>
    </main>

    <footer class="px-6 py-4 text-center text-micro text-ink-muted">
      © {{ new Date().getFullYear() }} Roofly.my · {{ t("common.tagline") }}
    </footer>
  </div>
</template>
