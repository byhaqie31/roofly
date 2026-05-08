<script setup lang="ts">
import type { Tenant, TenantStatus } from "~/types/tenant";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";

defineProps<{ tenant: Tenant }>();
const { t } = useI18n();

const statusTone = (status: TenantStatus) => {
  switch (status) {
    case "invited":
      return "draft";
    case "active":
      return "active";
    case "moved_out":
      return "expired";
  }
};
</script>

<template>
  <NuxtLink
    :to="`/owner/tenants/${tenant.id}`"
    class="block rounded-lg outline-none transition hover:opacity-95 focus-visible:shadow-focus"
  >
    <Card padding="standard">
      <div class="mb-3 flex items-start justify-between gap-3">
        <Pill :tone="statusTone(tenant.status)">
          {{ t(`owner.tenants.status.${tenant.status}`) }}
        </Pill>
      </div>
      <h3 class="truncate text-card-title text-ink">{{ tenant.name }}</h3>
      <div class="mt-2 flex flex-col gap-1 text-caption text-ink-muted">
        <span class="inline-flex items-center gap-1.5 truncate">
          <Icon name="Mail" :size="14" /> {{ tenant.email }}
        </span>
        <span class="inline-flex items-center gap-1.5">
          <Icon name="Phone" :size="14" /> {{ tenant.phone }}
        </span>
      </div>
    </Card>
  </NuxtLink>
</template>
