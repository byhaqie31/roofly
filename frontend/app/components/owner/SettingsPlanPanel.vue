<script setup lang="ts">
import type { Plan, PlanTier } from "~/types/owner";
import { useToast } from "~/composables/useToast";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";

defineProps<{
  plans: Plan[];
  currentTier: PlanTier;
}>();

const { t } = useI18n();
const { show } = useToast();

const onUpgrade = () => {
  show(t("owner.settings.plan.upgradeStub"), "default");
};
</script>

<template>
  <div class="space-y-6">
    <header>
      <h2 class="text-card-title font-semibold text-ink">
        {{ t("owner.settings.plan.title") }}
      </h2>
      <p class="mt-1 text-caption text-ink-muted">
        {{ t("owner.settings.plan.help") }}
      </p>
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article
          v-for="plan in plans"
          :key="plan.tier"
          :class="[
            'flex flex-col gap-3 rounded-lg border p-4 transition',
            plan.tier === currentTier
              ? 'border-ink bg-surface-page'
              : 'border-line-passive bg-surface-raised',
          ]"
        >
          <header class="flex items-center justify-between gap-2">
            <h3 class="text-card-title font-semibold text-ink">
              {{ t(`owner.settings.plan.tiers.${plan.tier}.name`) }}
            </h3>
            <Pill v-if="plan.tier === currentTier" tone="active">
              {{ t("owner.settings.plan.current") }}
            </Pill>
          </header>

          <p class="text-display-sub font-semibold tracking-snug text-ink">
            <span v-if="plan.priceRm === 0">
              {{ t("owner.settings.plan.free") }}
            </span>
            <span v-else>
              <span class="text-caption text-ink-muted">RM</span>
              {{ plan.priceRm }}
              <span class="text-caption text-ink-muted">
                {{ t("owner.settings.plan.permonth") }}
              </span>
            </span>
          </p>

          <ul class="flex flex-col gap-2 text-caption text-ink-muted">
            <li class="inline-flex items-center gap-2">
              <Icon name="Check" :size="14" class="text-ink-muted" />
              {{
                plan.unitsCap === "unlimited"
                  ? t("owner.settings.plan.unitsUnlimited")
                  : t("owner.settings.plan.unitsCap", { n: plan.unitsCap })
              }}
            </li>
            <li class="text-caption text-ink-muted">
              {{ t(`owner.settings.plan.tiers.${plan.tier}.description`) }}
            </li>
          </ul>

          <div class="mt-auto pt-2">
            <Button
              v-if="plan.tier !== currentTier"
              variant="ghost"
              size="sm"
              class="w-full"
              @click="onUpgrade"
            >
              {{ t("owner.settings.plan.upgradeCta") }}
            </Button>
            <p
              v-else
              class="text-center text-caption text-ink-muted"
            >
              {{ t("owner.settings.plan.youreOnThis") }}
            </p>
          </div>
        </article>
      </div>

      <p class="text-micro text-ink-faint">
        {{ t("owner.settings.plan.disclaimer") }}
      </p>
  </div>
</template>
