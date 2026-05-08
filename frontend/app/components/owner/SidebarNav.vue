<script setup lang="ts">
import {
  LayoutDashboard,
  Building2,
  Users,
  FileText,
  Receipt,
  Wrench,
  ChartBar,
  Settings,
} from "lucide-vue-next";

const { t } = useI18n();

const items = computed(() => [
  { to: "/owner", label: t("owner.nav.dashboard"), icon: LayoutDashboard, exact: true },
  { to: "/owner/properties", label: t("owner.nav.properties"), icon: Building2 },
  { to: "/owner/tenants", label: t("owner.nav.tenants"), icon: Users },
  { to: "/owner/agreements", label: t("owner.nav.agreements"), icon: FileText },
  { to: "/owner/payments", label: t("owner.nav.payments"), icon: Receipt },
  { to: "/owner/maintenance", label: t("owner.nav.maintenance"), icon: Wrench },
  { to: "/owner/reports", label: t("owner.nav.reports"), icon: ChartBar },
  { to: "/owner/settings", label: t("owner.nav.settings"), icon: Settings },
]);
</script>

<template>
  <nav class="flex flex-col gap-0.5">
    <NuxtLink
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      :exact-active-class="'bg-accent-soft text-accent'"
      :active-class="item.exact ? '' : 'bg-accent-soft text-accent'"
      class="flex items-center gap-3 px-4 py-2.5 rounded-sm text-caption text-ink-strong hover:bg-surface-hover focus-visible:shadow-focus transition"
    >
      <component :is="item.icon" :size="18" :stroke-width="1.5" />
      <span>{{ item.label }}</span>
    </NuxtLink>
  </nav>
</template>
