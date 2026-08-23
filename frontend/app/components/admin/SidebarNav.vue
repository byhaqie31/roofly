<script setup lang="ts">
import { LayoutDashboard, Building2, Users, ChartBar, ScrollText, Settings } from "lucide-vue-next";
import type { AdminPermission } from "~/types/admin";

const { t } = useI18n();
const { can } = useAdminPermissions();

type Item = { to: string; label: string; icon: unknown; exact?: boolean; needs?: AdminPermission };

const items = computed<Item[]>(() =>
  (
    [
      { to: "/admin", label: t("admin.nav.dashboard"), icon: LayoutDashboard, exact: true, needs: "dashboard.view" },
      { to: "/admin/owners", label: t("admin.nav.owners"), icon: Building2, needs: "owners.view" },
      { to: "/admin/tenants", label: t("admin.nav.tenants"), icon: Users, needs: "tenants.view" },
      { to: "/admin/analytics", label: t("admin.nav.analytics"), icon: ChartBar, needs: "analytics.view" },
      { to: "/admin/audit", label: t("admin.nav.audit"), icon: ScrollText },
      { to: "/admin/settings", label: t("admin.nav.settings"), icon: Settings, needs: "admins.manage" },
    ] as Item[]
  ).filter((i) => !i.needs || can(i.needs)),
);
</script>

<template>
  <nav class="flex flex-col gap-0.5">
    <NuxtLink
      v-for="item in items"
      :key="item.to"
      :to="item.to"
      :exact-active-class="'bg-admin-soft text-admin'"
      :active-class="item.exact ? '' : 'bg-admin-soft text-admin'"
      class="flex items-center gap-3 px-4 py-2.5 rounded-sm text-caption text-ink-strong hover:bg-surface-hover focus-visible:shadow-focus transition"
    >
      <component :is="item.icon" :size="18" :stroke-width="1.5" />
      <span>{{ item.label }}</span>
    </NuxtLink>
  </nav>
</template>
