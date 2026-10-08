<script setup lang="ts">
import Pill from "~/components/ui/Pill.vue";
import type { OwnerStatus } from "~/types/admin";
defineProps<{ status: OwnerStatus }>();
const { t } = useI18n();
</script>

<template>
  <Pill :tone="status === 'suspended' ? 'terminated' : 'active'">
    {{ t(`admin.status.owner.${status}`) }}
  </Pill>
</template>
