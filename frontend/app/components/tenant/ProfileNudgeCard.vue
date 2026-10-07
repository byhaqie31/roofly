<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";
import type { TenantProfileGaps } from "~/utils/tenantProfileCompletion";

/**
 * "Complete your profile" — top of the tenant home while any core field is
 * missing (spec 2026-10-07 § 4.4). Computed from the live profile, so it
 * disappears by itself once the details exist; no dismiss, no stored flag.
 */
defineProps<{ gaps: TenantProfileGaps }>();
const { t } = useI18n();
</script>

<template>
  <Card padding="loose" class="mb-4 sm:mb-6" data-testid="profile-nudge">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex min-w-0 items-start gap-3">
        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-pill bg-ink text-surface-page">
          <Icon name="UserRound" :size="16" />
        </span>
        <div class="min-w-0">
          <h2 class="text-card-title font-semibold text-ink">{{ t("tenant.home.profileCard.title") }}</h2>
          <p class="mt-1 text-caption text-ink-muted">{{ t("tenant.home.profileCard.hint") }}</p>
          <p class="mt-2 text-caption font-medium text-ink">
            {{ t("tenant.home.profileCard.progress", { done: gaps.done, total: gaps.total }) }}
          </p>
        </div>
      </div>
      <Button variant="primary" size="sm" class="self-start sm:self-auto" @click="navigateTo('/tenant/profile?edit=1')">
        {{ t("tenant.home.profileCard.cta") }}
        <Icon name="ArrowRight" :size="14" class="ml-1" />
      </Button>
    </div>
  </Card>
</template>
