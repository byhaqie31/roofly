<script setup lang="ts">
import ThemeToggle from "~/components/topbar/ThemeToggle.vue";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";
</script>

<template>
  <div class="min-h-dvh bg-surface-page text-ink flex flex-col">
    <header class="flex items-center justify-between px-6 py-4">
      <NuxtLink to="/" class="text-card-title font-semibold tracking-tight">
        Hauz
      </NuxtLink>
      <div class="flex items-center gap-1">
        <LangSwitcher />
        <ThemeToggle />
      </div>
    </header>
    <main class="flex-1 flex items-center justify-center px-6 py-10">
      <div class="w-full max-w-auth-card">
        <slot />
      </div>
    </main>
    <footer class="px-6 py-4 text-center text-micro text-ink-muted">
      © {{ new Date().getFullYear() }} Hauz. {{ $t("common.tagline") }}
    </footer>
  </div>
</template>
