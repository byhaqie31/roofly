<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";

/** Tenant home nudge while an agreement is awaiting the tenant's answer (spec 2026-10-07 agreement-review § 5). */
const { t } = useI18n();
</script>

<template>
  <Card padding="loose" class="mb-4 border-status-pending sm:mb-6" data-testid="agreement-review-nudge">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex min-w-0 items-start gap-3">
        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-pill bg-ink text-surface-page">
          <Icon name="FileSignature" :size="16" />
        </span>
        <div class="min-w-0">
          <h2 class="text-card-title font-semibold text-ink">{{ t("tenant.home.reviewCard.title") }}</h2>
          <p class="mt-1 text-caption text-ink-muted">{{ t("tenant.home.reviewCard.hint") }}</p>
        </div>
      </div>
      <Button variant="primary" size="sm" class="self-start sm:self-auto" @click="navigateTo('/tenant/agreement')">
        {{ t("tenant.home.reviewCard.cta") }}
        <Icon name="ArrowRight" :size="14" class="ml-1" />
      </Button>
    </div>
  </Card>
</template>
