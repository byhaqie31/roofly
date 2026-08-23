<script setup lang="ts">
import Pill from "~/components/ui/Pill.vue";
import type { LeadSource } from "~/types/analytics";

defineProps<{ source: LeadSource }>();
const { t } = useI18n();

const TONE: Record<LeadSource, "draft" | "maintenance" | "active"> = {
  waitlist: "draft",
  demo: "maintenance",
  register: "active",
};
</script>

<template>
  <Pill :tone="TONE[source]">
    {{ t(`admin.analytics.sources.${source}`) }}
  </Pill>
</template>
