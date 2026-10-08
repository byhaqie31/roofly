<script setup lang="ts">
import { PLANS } from "~/config/plans";

// Planned plan ladder for the Terms + Billing pages, read from config/plans.ts
// so prices aren't hardcoded in the legal copy.
const { t } = useI18n();
const { formatRM } = useMoney();
</script>

<template>
  <div class="overflow-hidden rounded-md border border-line-passive">
    <table class="w-full text-left text-caption">
      <thead class="bg-surface-raised text-ink-muted">
        <tr>
          <th scope="col" class="px-4 py-2 font-normal">{{ t("legal.plans.plan") }}</th>
          <th scope="col" class="px-4 py-2 font-normal text-right">{{ t("legal.plans.units") }}</th>
          <th scope="col" class="px-4 py-2 font-normal text-right">{{ t("legal.plans.price") }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="plan in PLANS" :key="plan.tier" class="border-t border-line-passive">
          <th scope="row" class="px-4 py-2 font-medium text-ink">{{ t(`owner.settings.plan.tiers.${plan.tier}.name`) }}</th>
          <td class="px-4 py-2 text-right tabular-nums text-ink-body">
            {{ plan.unitsCap === "unlimited" ? t("legal.plans.unlimited") : plan.unitsCap }}
          </td>
          <td class="px-4 py-2 text-right tabular-nums text-ink-body">{{ formatRM(plan.priceRm * 100) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
