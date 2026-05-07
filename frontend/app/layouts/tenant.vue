<script setup lang="ts">
import TenantSidebarNav from "~/components/tenant/SidebarNav.vue";
import ThemeToggle from "~/components/topbar/ThemeToggle.vue";
import LangSwitcher from "~/components/topbar/LangSwitcher.vue";
import UserMenu from "~/components/topbar/UserMenu.vue";
</script>

<template>
  <div class="min-h-dvh bg-surface-page text-ink flex">
    <aside
      class="hidden md:flex w-60 shrink-0 flex-col border-r border-line-passive px-3 py-4"
    >
      <NuxtLink
        to="/tenant"
        class="px-3 py-2 mb-4 text-card-title font-semibold tracking-tight"
      >
        Hauz
      </NuxtLink>
      <TenantSidebarNav />
    </aside>
    <div class="flex-1 flex flex-col min-w-0">
      <header
        class="h-16 flex items-center justify-end gap-1 px-6 border-b border-line-passive"
      >
        <LangSwitcher />
        <ThemeToggle />
        <UserMenu />
      </header>
      <main class="flex-1 px-6 py-8 overflow-auto">
        <div class="max-w-form-readable mx-auto">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>
