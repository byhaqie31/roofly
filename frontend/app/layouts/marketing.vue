<script setup lang="ts">
import { House } from "lucide-vue-next";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";
import SiteFooter from "~/components/layout/SiteFooter.vue";
</script>

<template>
  <!-- data-theme="dark" forces the dark token set on this subtree, so the
       marketing surface stays cinematic regardless of the user's global theme. -->
  <div
    data-theme="dark"
    class="min-h-dvh w-full"
    style="background-color: #1c1a17; color: #f7f4ed"
  >
    <!-- Subtle warm gradient wash so the page doesn't read as flat black -->
    <div
      class="fixed inset-0 pointer-events-none"
      style="
        background:
          radial-gradient(ellipse 50% 40% at 80% 0%, rgba(196, 77, 38, 0.18), transparent 60%),
          radial-gradient(ellipse 40% 35% at 20% 90%, rgba(196, 77, 38, 0.06), transparent 60%);
      "
    />

    <header class="relative z-10 flex items-center justify-between px-6 lg:px-12 py-5">
      <NuxtLink
        to="/coming-soon"
        class="inline-flex items-center gap-2 text-card-title font-semibold tracking-tight"
      >
        <House :size="22" :stroke-width="1.75" style="color: #c44d26" />
        <span>Roofly.my</span>
      </NuxtLink>
      <LangSwitcher />
    </header>

    <main class="relative z-10">
      <slot />
    </main>

    <SiteFooter tone="dark" />
  </div>
</template>
