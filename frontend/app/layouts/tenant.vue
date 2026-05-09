<script setup lang="ts">
import { House, Menu } from "lucide-vue-next";
import { ref } from "vue";
import TenantSidebarNav from "~/components/tenant/SidebarNav.vue";
import ThemeToggle from "~/components/topbar/ThemeToggle.vue";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";
import UserMenu from "~/components/topbar/UserMenu.vue";
import MobileNavDrawer from "~/components/layout/MobileNavDrawer.vue";

const drawerOpen = ref(false);
</script>

<template>
  <div class="min-h-dvh bg-surface-page text-ink flex">
    <!-- Desktop sidebar -->
    <aside
      class="hidden md:flex w-60 shrink-0 flex-col border-r border-line-passive px-3 py-4"
    >
      <NuxtLink
        to="/tenant"
        class="inline-flex items-center gap-2 px-3 py-2 mb-4 text-card-title font-semibold tracking-tight"
      >
        <House :size="20" :stroke-width="1.75" fill="currentColor" fill-opacity="0.2" class="text-accent" />
        <span>Roofly.my</span>
      </NuxtLink>
      <TenantSidebarNav />
    </aside>

    <!-- Mobile drawer -->
    <MobileNavDrawer v-model="drawerOpen" home-to="/tenant">
      <TenantSidebarNav />
    </MobileNavDrawer>

    <div class="flex-1 flex flex-col min-w-0">
      <header
        class="h-16 flex items-center justify-between gap-2 px-4 md:px-6 border-b border-line-passive"
      >
        <!-- Mobile-only: hamburger + wordmark -->
        <div class="flex items-center gap-2 md:hidden">
          <button
            type="button"
            class="inline-flex items-center justify-center w-9 h-9 rounded-sm text-ink-strong hover:bg-[rgba(28,28,28,0.04)] focus-visible:shadow-focus transition"
            aria-label="Open menu"
            @click="drawerOpen = true"
          >
            <Menu :size="22" :stroke-width="1.5" />
          </button>
          <NuxtLink
            to="/tenant"
            class="inline-flex items-center gap-2 text-card-title font-semibold tracking-tight"
          >
            <House :size="20" :stroke-width="1.75" fill="currentColor" fill-opacity="0.2" class="text-accent" />
            <span>Roofly.my</span>
          </NuxtLink>
        </div>

        <div class="flex items-center gap-1 ml-auto">
          <div class="hidden md:inline-flex md:items-center md:gap-1">
            <LangSwitcher />
            <ThemeToggle />
          </div>
          <UserMenu />
        </div>
      </header>
      <main class="flex-1 px-4 md:px-6 py-8 overflow-auto">
        <div class="max-w-form-readable mx-auto">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
