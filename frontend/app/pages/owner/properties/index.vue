<script setup lang="ts">
import { onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";
import AddPropertyModal from "~/components/owner/AddPropertyModal.vue";
import PropertyCard from "~/components/owner/PropertyCard.vue";
import type { Property } from "~/types/property";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("owner.nav.properties") });

const showAddModal = ref(false);
const properties = ref<Property[]>([]);
const loading = ref(true);

onMounted(async () => {
  try {
    properties.value = await useProperties().list();
  } finally {
    loading.value = false;
  }
});

const onCreated = (property: Property) => {
  properties.value.unshift(property);
};
</script>

<template>
  <div>
    <header class="mb-8 flex items-end justify-between">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.nav.properties") }}
      </h1>
      <Button variant="primary" @click="showAddModal = true">
        + {{ t("owner.properties.addCta") }}
      </Button>
    </header>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("common.loading") }}
      </p>
    </Card>

    <Card v-else-if="properties.length === 0" padding="loose">
      <EmptyState
        icon="Building2"
        :title="t('owner.dashboard.emptyState.title')"
        :description="t('owner.dashboard.emptyState.description')"
      />
    </Card>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <PropertyCard
        v-for="p in properties"
        :key="p.id"
        :property="p"
      />
    </div>

    <AddPropertyModal v-model:open="showAddModal" @created="onCreated" />
  </div>
</template>
