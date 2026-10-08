<script setup lang="ts">
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";
import RotatingUspCard from "~/components/demo/RotatingUspCard.vue";
import AnimatedStatBand from "~/components/demo/AnimatedStatBand.vue";
import AudienceFlipCard from "~/components/demo/AudienceFlipCard.vue";
import AuthPromoVideo from "~/components/auth/AuthPromoVideo.vue";
import SiteFooter from "~/components/layout/SiteFooter.vue";
import SiteHeader from "~/components/layout/SiteHeader.vue";

// Falls back to the old headline + USP deck if the promo file is missing.
const promoUnavailable = ref(false);
</script>

<template>
  <!-- data-theme="light" forces the light token set on this subtree, so
       the form pane stays cream regardless of the user's global theme. -->
  <div data-theme="light" class="min-h-dvh flex flex-col">
    <div class="flex-1 flex flex-col md:flex-row">
      <!-- Marketing pane — always charcoal, hidden on mobile -->
      <aside
        class="hidden md:flex md:w-1/2 lg:w-3/5 relative flex-col justify-between"
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

        <!-- Top: wordmark, in the same spot as every public page (SiteHeader). -->
        <SiteHeader icon-color="#c44d26" />

        <!-- Center: the promo reel (AuthPromoVideo). If its file is missing,
             fall back to the audience-flipping headline. -->
        <div class="relative px-10 py-10 lg:px-14">
          <AuthPromoVideo v-if="!promoUnavailable" class="w-full" @unavailable="promoUnavailable = true" />
          <AudienceFlipCard v-else />
        </div>

        <!-- Bottom: animated stat cards, same width as the reel (plus the rotating
             USP deck when the reel is unavailable) — same on demo, uat, and prod. -->
        <div class="relative px-10 pb-10 lg:px-14 lg:pb-14">
          <RotatingUspCard v-if="promoUnavailable" class="mb-8" />
          <AnimatedStatBand class="w-full max-w-3xl mx-auto" />
        </div>
      </aside>

      <!-- Form pane — always light, no theme toggle -->
      <div class="flex-1 flex flex-col bg-surface-page text-ink">
        <!-- Wordmark shows here on mobile only (the charcoal pane has it from md:). -->
        <SiteHeader wordmark-class="md:hidden">
          <LangSwitcher />
        </SiteHeader>

        <main class="flex-1 flex items-center justify-center px-6 py-10">
          <div class="w-full max-w-auth-card">
            <slot />
          </div>
        </main>
      </div>
    </div>

    <!-- Full-width slim footer (© + Privacy / Terms), its own band under both
         panes rather than part of the marketing pane. -->
    <div style="background-color: #1c1a17">
      <SiteFooter tone="dark" variant="slim" />
    </div>
  </div>
</template>
