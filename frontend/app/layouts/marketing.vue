<script setup lang="ts">
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";
import SiteFooter from "~/components/layout/SiteFooter.vue";
import SiteHeader from "~/components/layout/SiteHeader.vue";
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

    <SiteHeader icon-color="#c44d26">
      <LangSwitcher />
    </SiteHeader>

    <main class="relative z-10">
      <slot />
    </main>

    <SiteFooter tone="dark" variant="full" />
  </div>
</template>
