<script setup lang="ts">
import {
  DoorOpen,
  FileText,
  Receipt,
  Wrench,
  User as UserIcon,
} from "lucide-vue-next";

const { t } = useI18n();

const items = computed(() => [
  { to: "/tenant", label: t("tenant.nav.home"), icon: DoorOpen, exact: true },
  { to: "/tenant/agreement", label: t("tenant.nav.agreement"), icon: FileText },
  { to: "/tenant/payments", label: t("tenant.nav.payments"), icon: Receipt },
  { to: "/tenant/tickets", label: t("tenant.nav.tickets"), icon: Wrench },
  { to: "/tenant/profile", label: t("tenant.nav.profile"), icon: UserIcon },
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
      class="flex items-center gap-3 px-4 py-2.5 rounded-sm text-caption text-ink-strong hover:bg-[rgba(28,28,28,0.04)] focus-visible:shadow-focus transition"
    >
      <component :is="item.icon" :size="18" :stroke-width="1.5" />
      <span>{{ item.label }}</span>
    </NuxtLink>
  </nav>
</template>
