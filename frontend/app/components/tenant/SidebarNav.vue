<script setup lang="ts">
import {
  DoorOpen,
  FileText,
  Receipt,
  Wrench,
  User as UserIcon,
  LifeBuoy,
} from "lucide-vue-next";

const { t } = useI18n();

const items = computed(() => [
  { to: "/tenant", label: t("tenant.nav.home"), icon: DoorOpen, exact: true },
  // Profile sits right after Home: it's the first thing a new tenant must finish.
  { to: "/tenant/profile", label: t("tenant.nav.profile"), icon: UserIcon },
  { to: "/tenant/agreement", label: t("tenant.nav.agreement"), icon: FileText },
  { to: "/tenant/payments", label: t("tenant.nav.payments"), icon: Receipt },
  { to: "/tenant/tickets", label: t("tenant.nav.tickets"), icon: Wrench },
]);
// Pinned to the bottom of the sidebar, apart from the main navigation.
const help = computed(() => ({ to: "/tenant/help", label: t("tenant.nav.help"), icon: LifeBuoy }));

const linkClass =
  "flex items-center gap-3 px-4 py-2.5 rounded-sm text-caption text-ink-strong hover:bg-surface-hover focus-visible:shadow-focus transition";
</script>

<template>
  <!-- Main navigation on top; "Help and support" pinned to the bottom of the
       sidebar / drawer (UI-STANDARDS § 3.7). -->
  <nav class="flex flex-1 flex-col gap-0.5">
    <NuxtLink
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      :exact-active-class="'bg-accent-soft text-accent'"
      :active-class="item.exact ? '' : 'bg-accent-soft text-accent'"
      :class="linkClass"
    >
      <component :is="item.icon" :size="18" :stroke-width="1.5" />
      <span>{{ item.label }}</span>
    </NuxtLink>

    <div class="mt-auto border-t border-line-passive pt-3">
      <NuxtLink :to="help.to" active-class="bg-accent-soft text-accent" :class="linkClass">
        <component :is="help.icon" :size="18" :stroke-width="1.5" />
        <span>{{ help.label }}</span>
      </NuxtLink>
    </div>
  </nav>
</template>
