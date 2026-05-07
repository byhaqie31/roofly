<script setup lang="ts">
import { House, Eye, Zap, ShieldCheck } from "lucide-vue-next";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";

const { t } = useI18n();

const pillars = computed(() => [
  { icon: Eye, label: t("common.marketing.pillarVisibility") },
  { icon: Zap, label: t("common.marketing.pillarAutomation") },
  { icon: ShieldCheck, label: t("common.marketing.pillarTrust") },
]);
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
        <span>Hauz.my</span>
      </NuxtLink>

      <!-- Center: editorial headline + sub -->
      <div class="relative max-w-[36rem]">
        <h1
          class="text-display-section lg:text-display-hero font-semibold tracking-tight leading-[1.05]"
          style="color: #f7f4ed"
        >
          <span class="block">{{ t("common.marketing.headlineLead") }}</span>
          <span class="block" style="color: #e76a3f">
            {{ t("common.marketing.headlineAccent") }}
          </span>
        </h1>
        <p
          class="mt-6 text-body-lg max-w-[34rem]"
          style="color: rgba(247, 244, 237, 0.88)"
        >
          {{ t("common.marketing.subhead") }}
        </p>
      </div>

      <!-- Bottom: pillars + footer -->
      <div class="relative">
        <ul class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-10">
          <li
            v-for="(p, i) in pillars"
            :key="i"
            class="flex items-center gap-3"
          >
            <span
              class="inline-flex items-center justify-center w-9 h-9 rounded-pill shrink-0"
              style="
                background: rgba(231, 106, 63, 0.16);
                color: #e76a3f;
                box-shadow: inset 0 0 0 1px rgba(231, 106, 63, 0.18);
              "
            >
              <component :is="p.icon" :size="18" :stroke-width="1.75" />
            </span>
            <span
              class="text-caption leading-snug flex-1"
              style="color: rgba(247, 244, 237, 0.95)"
            >
              {{ p.label }}
            </span>
          </li>
        </ul>
        <p
          class="text-micro"
          style="color: rgba(247, 244, 237, 0.6)"
        >
          © {{ new Date().getFullYear() }} Hauz.my · {{ t("common.tagline") }}
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
          <span>Hauz.my</span>
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
        © {{ new Date().getFullYear() }} Hauz.my · {{ $t("common.tagline") }}
      </footer>
    </div>
  </div>
</template>
