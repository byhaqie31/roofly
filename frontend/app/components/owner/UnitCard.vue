<script setup lang="ts">
import type { Unit } from "~/types/unit";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";

defineProps<{ unit: Unit }>();
const { t } = useI18n();
</script>

<template>
  <button
    type="button"
    class="block w-full text-left rounded-lg outline-none transition hover:opacity-95 focus-visible:shadow-focus"
  >
    <Card padding="compact">
      <div class="mb-2">
        <Pill :tone="unit.status">
          {{ t(`owner.units.status.${unit.status}`) }}
        </Pill>
      </div>
      <h4 class="truncate text-card-title text-ink">{{ unit.label }}</h4>
      <div
        class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-ink-muted"
      >
        <span
          v-if="unit.bedrooms != null"
          class="inline-flex items-center gap-1"
          :title="t('owner.units.fields.bedrooms')"
        >
          <Icon name="BedDouble" :size="14" /> {{ unit.bedrooms }}
        </span>
        <span
          v-if="unit.bathrooms != null"
          class="inline-flex items-center gap-1"
          :title="t('owner.units.fields.bathrooms')"
        >
          <Icon name="Bath" :size="14" /> {{ unit.bathrooms }}
        </span>
        <span
          v-if="unit.sqft != null"
          class="inline-flex items-center gap-1"
          :title="t('owner.units.fields.sqft')"
        >
          <Icon name="Maximize" :size="14" /> {{ unit.sqft }} sqft
        </span>
      </div>
    </Card>
  </button>
</template>
