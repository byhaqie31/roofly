<script setup lang="ts">
import type { Property, PropertyType } from "~/types/property";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";

defineProps<{ property: Property }>();

const { t } = useI18n();

const typeIcon = (type: PropertyType) => {
  switch (type) {
    case "condo":
      return "Building2";
    case "landed":
      return "Home";
    case "shoplot":
      return "Store";
    case "room":
      return "BedDouble";
  }
};
</script>

<template>
  <NuxtLink
    :to="`/owner/properties/${property.id}`"
    class="block rounded-lg outline-none transition hover:opacity-90 focus-visible:shadow-focus"
  >
    <Card padding="standard">
      <div class="mb-3 flex items-start justify-between gap-3">
        <Pill tone="neutral">
          <Icon
            :name="typeIcon(property.type)"
            :size="12"
            class="mr-1"
          />
          {{ t(`owner.properties.types.${property.type}`) }}
        </Pill>
      </div>
      <h3 class="truncate text-card-title text-ink">{{ property.name }}</h3>
      <p class="mt-1 line-clamp-2 text-caption text-ink-muted">
        {{ property.address }}
      </p>
      <p class="mt-2 text-micro text-ink-faint">
        {{ property.city }}, {{ property.state }} · {{ property.postcode }}
      </p>
    </Card>
  </NuxtLink>
</template>
