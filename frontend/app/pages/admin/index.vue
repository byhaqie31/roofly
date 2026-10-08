<script setup lang="ts">
import { onMounted } from "vue";
import Card from "~/components/ui/Card.vue";
import MiniAreaChart from "~/components/ui/MiniAreaChart.vue";
import StatTile from "~/components/admin/StatTile.vue";
import AttentionList from "~/components/admin/AttentionList.vue";

import NoAccess from "~/components/admin/NoAccess.vue";

definePageMeta({ layout: "admin" });
const { can } = useAdminPermissions();
const { t } = useI18n();
useHead({ title: () => t("admin.dashboard.title") });

const dash = useAdminDashboardData();
onMounted(() => { if (can("dashboard.view")) dash.load(); });

const count = (n: number) => String(n);
const pct = (n: number) => `${n}%`;
</script>

<template>
  <NoAccess v-if="!can('dashboard.view')" permission="dashboard.view" />
  <div v-else>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.dashboard.title") }}</h1>
      <p class="mt-2 text-caption text-ink-muted">{{ t("admin.dashboard.subtitle") }}</p>
    </header>

    <Card v-if="dash.loading.value" padding="loose">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <template v-else>
      <section class="mb-6 grid grid-cols-1 gap-4 sm:mb-8 sm:grid-cols-2 sm:gap-6 xl:grid-cols-3 2xl:grid-cols-5">
        <StatTile :label="t('admin.dashboard.tiles.owners')" :value="dash.tiles.value.owners.total"
          :help="t('admin.dashboard.tiles.ownersHelp', { active: dash.tiles.value.owners.active, suspended: dash.tiles.value.owners.suspended })" />
        <StatTile :label="t('admin.dashboard.tiles.tenants')" :value="dash.tiles.value.tenants.total"
          :help="t('admin.dashboard.tiles.tenantsHelp', { pending: dash.tiles.value.tenants.invitedPending })" />
        <StatTile :label="t('admin.dashboard.tiles.properties')" :value="dash.tiles.value.properties"
          :help="t('admin.dashboard.tiles.propertiesHelp')" />
        <StatTile :label="t('admin.dashboard.tiles.units')" :value="`${dash.tiles.value.units.occupiedPct}%`"
          :help="t('admin.dashboard.tiles.unitsHelp', { total: dash.tiles.value.units.total })" />
        <StatTile :label="t('admin.dashboard.tiles.agreements')" :value="dash.tiles.value.agreementsActive"
          :help="t('admin.dashboard.tiles.agreementsHelp', { expiring: dash.tiles.value.agreementsExpiring30d })" />
      </section>

      <section class="mb-6 grid grid-cols-1 gap-4 sm:mb-8 sm:gap-6 lg:grid-cols-3">
        <Card padding="loose">
          <h2 class="text-card-title font-semibold text-ink">{{ t("admin.dashboard.charts.signups") }}</h2>
          <MiniAreaChart class="mt-4" :data="dash.signupSeries.value" :height="100" :format="count" :show-average-line="false" />
        </Card>
        <Card padding="loose">
          <h2 class="text-card-title font-semibold text-ink">{{ t("admin.dashboard.charts.invoicesPaid") }}</h2>
          <MiniAreaChart class="mt-4" :data="dash.invoiceSeries.value" :height="100" :format="count" :show-average-line="false" />
        </Card>
        <Card padding="loose">
          <h2 class="text-card-title font-semibold text-ink">{{ t("admin.dashboard.charts.acceptance") }}</h2>
          <MiniAreaChart class="mt-4" :data="dash.acceptanceSeries.value" :height="100" :format="pct" :show-average-line="false" />
        </Card>
      </section>

      <section>
        <Card padding="loose">
          <header class="mb-4">
            <h2 class="text-card-title font-semibold text-ink">{{ t("admin.dashboard.attention.title") }}</h2>
            <p class="mt-1 text-caption text-ink-muted">{{ t("admin.dashboard.attention.help") }}</p>
          </header>
          <p v-if="dash.attention.value.length === 0" class="text-body text-ink-muted">{{ t("admin.dashboard.attention.empty") }}</p>
          <AttentionList v-else :items="dash.attention.value" />
        </Card>
      </section>
    </template>
  </div>
</template>
