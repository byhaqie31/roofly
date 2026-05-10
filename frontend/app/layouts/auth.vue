<script setup lang="ts">
import { House } from "lucide-vue-next";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";
import RotatingUspCard from "~/components/demo/RotatingUspCard.vue";
import AnimatedStatBand from "~/components/demo/AnimatedStatBand.vue";
import AudienceFlipCard from "~/components/demo/AudienceFlipCard.vue";

const { t } = useI18n();
</script>

<template>
  <!-- data-theme="light" forces the light token set on this subtree, so
       the form pane stays cream regardless of the user's global theme. -->
  <div data-theme="light" class="min-h-dvh flex flex-col md:flex-row">
    <!-- Marketing pane — always charcoal, hidden on mobile -->
    <aside
      class="hidden md:flex md:w-1/2 lg:w-3/5 relative flex-col justify-between p-10 lg:p-14"
      style="background-color: #1c1a17; color: #f7f4ed"
    >
      <!-- Subtle warm gradient wash -->
      <div
        class="absolute inset-0 pointer-events-none"
        style="
          background:
            radial-gradient(ellipse 60% 50% at 90% 0%, rgba(196,77,38,0.22), transparent 65%),
            radial-gradient(ellipse 50% 40% at 10% 100%, rgba(196,77,38,0.08), transparent 65%);
        "
      />

      <!-- Top: wordmark -->
      <NuxtLink
        to="/"
        class="relative inline-flex items-center gap-2 text-card-title font-semibold tracking-tight w-fit"
      >
        <House :size="22" :stroke-width="1.75" style="color: #c44d26" />
        <span>Roofly.my</span>
      </NuxtLink>

      <!-- Center: audience-flipping headline + description.
           Auto-flips every 4.5s between owner and tenant value props with a
           vertical slide. Hover to pause. Replaces the old static H1+subhead. -->
      <div class="relative">
        <AudienceFlipCard />
      </div>

      <!-- Bottom: marketing block + footer. Rotating USP deck + animated
           stat band — same on demo, uat, and prod. -->
      <div class="relative">
        <RotatingUspCard class="mb-8" />
        <AnimatedStatBand class="mb-10" />
        <p
          class="text-micro"
          style="color: rgba(247, 244, 237, 0.6)"
        >
          © {{ new Date().getFullYear() }} Roofly.my · {{ t("common.tagline") }}
        </p>
      </div>
    </aside>

    <!-- Form pane — always light, no theme toggle -->
    <div class="flex-1 flex flex-col bg-surface-page text-ink min-h-dvh">
      <header class="flex items-center justify-between px-6 py-4">
        <!-- Mobile-only wordmark -->
        <NuxtLink
          to="/"
          class="md:hidden inline-flex items-center gap-2 text-card-title font-semibold tracking-tight"
        >
          <House :size="22" :stroke-width="1.75" class="text-accent" />
          <span>Roofly.my</span>
        </NuxtLink>
        <div class="flex items-center gap-1 ml-auto">
          <LangSwitcher />
        </div>
      </header>

      <main class="flex-1 flex items-center justify-center px-6 py-10">
        <div class="w-full max-w-auth-card">
          <slot />
        </div>
      </main>

      <footer class="px-6 py-4 text-center text-micro text-ink-muted md:hidden">
        © {{ new Date().getFullYear() }} Roofly.my · {{ $t("common.tagline") }}
      </footer>
    </div>
  </div>
</template>
