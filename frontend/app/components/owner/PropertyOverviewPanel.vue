<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { AgreementWithRefs } from "~/services/useAgreements";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";

const props = defineProps<{ property: Property }>();

const { t } = useI18n();
const { formatRM } = useMoney();

const units = ref<Unit[]>([]);
const agreements = ref<AgreementWithRefs[]>([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const [u, a] = await Promise.all([
      useUnits().listByProperty(props.property.id),
      useAgreements().listWithRefs(),
    ]);
    units.value = u;
    agreements.value = a.filter((row) =>
      u.some((unit) => unit.id === row.agreement.unitId),
    );
  } finally {
    loading.value = false;
  }
});

const unitCounts = computed(() => {
  const counts = { vacant: 0, occupied: 0, maintenance: 0 };
  units.value.forEach((u) => {
    counts[u.status] += 1;
  });
  return counts;
});

const activeAgreements = computed(() =>
  agreements.value.filter((row) => row.agreement.status === "active"),
);

const monthlyIncome = computed(() =>
  activeAgreements.value.reduce(
    (sum, row) => sum + row.agreement.rentAmount,
    0,
  ),
);
</script>

<template>
  <div class="space-y-6">
    <p class="text-caption text-ink-muted">
      {{ t("owner.properties.detail.overviewHelp") }}
    </p>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
      <div class="rounded-md border border-line-passive bg-surface-page p-4">
        <div class="text-caption text-ink-muted">
          {{ t("owner.properties.detail.overview.totalUnits") }}
        </div>
        <div class="mt-1 text-card-title font-semibold text-ink tabular-nums">
          {{ units.length }}
        </div>
      </div>
      <div class="rounded-md border border-line-passive bg-surface-page p-4">
        <div class="text-caption text-ink-muted">
          {{ t("owner.properties.detail.overview.occupied") }}
        </div>
        <div class="mt-1 text-card-title font-semibold text-status-active tabular-nums">
          {{ unitCounts.occupied }}
        </div>
      </div>
      <div class="rounded-md border border-line-passive bg-surface-page p-4">
        <div class="text-caption text-ink-muted">
          {{ t("owner.properties.detail.overview.activeAgreements") }}
        </div>
        <div class="mt-1 text-card-title font-semibold text-ink tabular-nums">
          {{ activeAgreements.length }}
        </div>
      </div>
      <div class="rounded-md border border-line-passive bg-surface-page p-4">
        <div class="text-caption text-ink-muted">
          {{ t("owner.properties.detail.overview.monthlyIncome") }}
        </div>
        <div class="mt-1 text-card-title font-semibold text-ink tabular-nums">
          {{ formatRM(monthlyIncome) }}
        </div>
      </div>
    </div>

    <section v-if="!loading && activeAgreements.length > 0" class="space-y-3">
      <h3
        class="text-caption font-semibold uppercase tracking-wide text-ink-muted"
      >
        {{ t("owner.properties.detail.overview.activeTenants") }}
      </h3>
      <ul class="divide-y divide-line-passive">
        <li
          v-for="row in activeAgreements"
          :key="row.agreement.id"
          class="flex items-center justify-between gap-3 py-3"
        >
          <div class="min-w-0">
            <div class="truncate text-body text-ink">
              {{ row.tenant?.name ?? t("owner.agreements.unknownTenant") }}
            </div>
            <div class="truncate text-caption text-ink-muted">
              <Icon name="Home" :size="12" class="mr-1 inline" />
              {{ row.unit?.label ?? "—" }}
            </div>
          </div>
          <div class="text-right">
            <div class="text-body tabular-nums text-ink">
              {{ formatRM(row.agreement.rentAmount) }}
              <span class="text-ink-faint">/mo</span>
            </div>
            <Pill tone="active" class="mt-1">
              {{ t(`owner.agreements.status.${row.agreement.status}`) }}
            </Pill>
          </div>
        </li>
      </ul>
    </section>

    <p
      v-if="!loading && activeAgreements.length === 0"
      class="rounded-md border border-line-passive bg-surface-page px-4 py-6 text-center text-caption text-ink-muted"
    >
      {{ t("owner.properties.detail.overview.noActive") }}
    </p>
  </div>
</template>
