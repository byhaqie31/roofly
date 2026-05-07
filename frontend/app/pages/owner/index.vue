<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";
import MoneyDisplay from "~/components/ui/MoneyDisplay.vue";

definePageMeta({ layout: "owner" });

const { t } = useI18n();
useHead({ title: () => t("owner.dashboard.title") });
</script>

<template>
  <div>
    <header class="mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.dashboard.title") }}
      </h1>
    </header>

    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
      <Card padding="standard">
        <p class="text-caption text-ink-muted">{{ t("owner.dashboard.incomeMonth") }}</p>
        <p class="mt-2 text-display-sub font-semibold tracking-snug">
          <MoneyDisplay :cents="0" emphasis />
        </p>
      </Card>
      <Card padding="standard">
        <p class="text-caption text-ink-muted">{{ t("owner.dashboard.occupancy") }}</p>
        <p class="mt-2 text-display-sub font-semibold tracking-snug num">0%</p>
      </Card>
      <Card padding="standard">
        <p class="text-caption text-ink-muted">{{ t("owner.dashboard.outstanding") }}</p>
        <p class="mt-2 text-display-sub font-semibold tracking-snug">
          <MoneyDisplay :cents="0" emphasis />
        </p>
      </Card>
      <Card padding="standard">
        <p class="text-caption text-ink-muted">{{ t("owner.dashboard.expiringAgreements") }}</p>
        <p class="mt-2 text-display-sub font-semibold tracking-snug num">0</p>
      </Card>
    </section>

    <Card padding="loose">
      <EmptyState
        icon="Building2"
        :title="t('owner.dashboard.emptyState.title')"
        :description="t('owner.dashboard.emptyState.description')"
      >
        <template #action>
          <NuxtLink to="/owner/properties">
            <Button variant="primary" size="lg">
              {{ t("owner.dashboard.emptyState.cta") }}
            </Button>
          </NuxtLink>
        </template>
      </EmptyState>
    </Card>
  </div>
</template>
