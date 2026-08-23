<script setup lang="ts">
import { ShieldCheck, Menu } from "lucide-vue-next";
import { onMounted, ref } from "vue";
import AdminSidebarNav from "~/components/admin/SidebarNav.vue";
import ThemeToggle from "~/components/topbar/ThemeToggle.vue";
import UserMenu from "~/components/topbar/UserMenu.vue";
import MobileNavDrawer from "~/components/layout/MobileNavDrawer.vue";

const drawerOpen = ref(false);
const { t } = useI18n();
// Admin is English-only (internal ops tool): pin the locale and hide the switcher.
const { locale, setLocale } = useI18n();
onMounted(() => { if (locale.value !== "en") setLocale("en"); });
</script>

<template>
  <div class="min-h-dvh bg-surface-page text-ink flex border-t-4 border-admin">
    <aside class="hidden md:flex w-64 shrink-0 flex-col border-r border-line-passive px-3 py-4">
      <NuxtLink to="/admin" class="inline-flex items-center gap-2 px-3 py-2 mb-4 text-card-title font-semibold tracking-tight">
        <ShieldCheck :size="20" :stroke-width="1.75" class="text-admin" />
        <span>Roofly.my</span>
        <span class="ml-1 rounded-pill bg-admin-soft px-2 py-0.5 text-micro font-medium text-admin">{{ t("admin.nav.badge") }}</span>
      </NuxtLink>
      <AdminSidebarNav />
    </aside>

    <MobileNavDrawer v-model="drawerOpen" home-to="/admin">
      <AdminSidebarNav />
    </MobileNavDrawer>

    <div class="flex-1 flex flex-col min-w-0">
      <header class="h-16 flex items-center justify-between gap-2 px-4 md:px-6 border-b border-line-passive">
        <div class="flex items-center gap-2 md:hidden">
          <button
            type="button"
            class="inline-flex items-center justify-center w-9 h-9 rounded-sm text-ink-strong hover:bg-surface-hover focus-visible:shadow-focus transition"
            aria-label="Open menu"
            @click="drawerOpen = true"
          >
            <Menu :size="22" :stroke-width="1.5" />
          </button>
          <NuxtLink to="/admin" class="inline-flex items-center gap-2 text-card-title font-semibold tracking-tight">
            <ShieldCheck :size="20" :stroke-width="1.75" class="text-admin" />
            <span>Roofly.my</span>
          </NuxtLink>
        </div>
        <div class="flex items-center gap-2 ml-auto">
          <div class="hidden md:inline-flex md:items-center md:gap-1">
            <ThemeToggle />
          </div>
          <UserMenu :show-locale="false" />
        </div>
      </header>
      <main class="flex-1 px-4 md:px-6 py-8 overflow-auto">
        <div class="max-w-app mx-auto"><slot /></div>
      </main>
    </div>
  </div>
</template>
