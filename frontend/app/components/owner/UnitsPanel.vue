<script setup lang="ts">
import { onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import UnitCard from "~/components/owner/UnitCard.vue";
import UnitFormModal from "~/components/owner/UnitFormModal.vue";
import type { Unit } from "~/types/unit";

const props = defineProps<{ propertyId: string }>();

const { t } = useI18n();
const units = ref<Unit[]>([]);
const loading = ref(true);
const showModal = ref(false);
const editingUnit = ref<Unit | null>(null);

const refresh = async () => {
  units.value = await useUnits().listByProperty(props.propertyId);
};

onMounted(async () => {
  try {
    await refresh();
  } finally {
    loading.value = false;
  }
});

const onAdd = () => {
  editingUnit.value = null;
  showModal.value = true;
};

const onEdit = (unit: Unit) => {
  editingUnit.value = unit;
  showModal.value = true;
};

const onSaved = async () => {
  await refresh();
};

const onDeleted = async () => {
  await refresh();
};
</script>

<template>
  <div class="mt-6">
    <Card padding="loose">
      <header class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h2 class="text-card-title font-semibold text-ink">
            {{ t("owner.units.sectionTitle") }}
            <span v-if="!loading" class="font-normal text-ink-muted">
              ({{ units.length }})
            </span>
          </h2>
          <p class="mt-1 text-caption text-ink-muted">
            {{ t("owner.units.sectionHelp") }}
          </p>
        </div>
        <Button variant="ghost" class="self-start" @click="onAdd">
          + {{ t("owner.units.addCta") }}
        </Button>
      </header>

      <p
        v-if="loading"
        class="py-6 text-center text-body text-ink-muted"
      >
        {{ t("common.loading") }}
      </p>

      <div v-else-if="units.length === 0" class="py-6 text-center">
        <p class="text-body text-ink-muted">
          {{ t("owner.units.emptyTitle") }}
        </p>
        <p class="mt-1 text-caption text-ink-faint">
          {{ t("owner.units.emptyHelp") }}
        </p>
      </div>

      <div
        v-else
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
      >
        <UnitCard
          v-for="u in units"
          :key="u.id"
          :unit="u"
          @click="onEdit(u)"
        />
      </div>
    </Card>

    <UnitFormModal
      v-model:open="showModal"
      :property-id="propertyId"
      :unit="editingUnit"
      @saved="onSaved"
      @deleted="onDeleted"
    />
  </div>
</template>
