<script setup lang="ts">
import { ShieldCheck } from "lucide-vue-next";
import { onMounted } from "vue";
import SiteFooter from "~/components/layout/SiteFooter.vue";
import SiteHeader from "~/components/layout/SiteHeader.vue";

const { t } = useI18n();
// Admin is English-only (internal ops tool): pin the locale and hide the switcher.
const { locale, setLocale } = useI18n();
onMounted(() => { if (locale.value !== "en") setLocale("en"); });
</script>

<template>
  <!-- Charcoal page, light card: deliberately not the customer auth chrome (spec § 3). -->
  <div
    class="min-h-dvh flex flex-col"
    style="background-color: #1c1a17; color: #f7f4ed"
  >
    <SiteHeader to="/admin/login" :icon="ShieldCheck" icon-color="#7fa6c9" :label="`Roofly.my · ${t('auth.admin.title')}`" />

    <main class="flex-1 flex items-center justify-center px-6 py-10">
      <div
        data-theme="light"
        class="w-full max-w-auth-card rounded-xl border border-line-passive bg-surface-raised text-ink p-8 shadow-modal"
      >
        <slot />
      </div>
    </main>

    <SiteFooter tone="dark" variant="admin" />
  </div>
</template>
