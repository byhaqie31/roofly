<script setup lang="ts">
import { ShieldCheck } from "lucide-vue-next";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";

const { t } = useI18n();
</script>

<template>
  <!-- Charcoal page, light card: deliberately not the customer auth chrome (spec § 3). -->
  <div
    class="min-h-dvh flex flex-col"
    style="background-color: #1c1a17; color: #f7f4ed"
  >
    <header class="flex items-center justify-between px-6 py-4">
      <NuxtLink to="/admin/login" class="inline-flex items-center gap-2 text-card-title font-semibold tracking-tight">
        <ShieldCheck :size="22" :stroke-width="1.75" style="color: #7fa6c9" />
        <span>Roofly.my · {{ t("auth.admin.title") }}</span>
      </NuxtLink>
      <div data-theme="dark"><LangSwitcher /></div>
    </header>

    <main class="flex-1 flex items-center justify-center px-6 py-10">
      <div
        data-theme="light"
        class="w-full max-w-auth-card rounded-xl border border-line-passive bg-surface-raised text-ink p-8 shadow-modal"
      >
        <slot />
      </div>
    </main>

    <footer class="px-6 py-4 text-center text-micro" style="color: rgba(247, 244, 237, 0.6)">
      © {{ new Date().getFullYear() }} Roofly.my · {{ t("common.tagline") }}
    </footer>
  </div>
</template>
