<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import Icon from "~/components/ui/Icon.vue";
import type { ChecklistStep } from "~/utils/onboardingChecklist";

const props = defineProps<{ steps: ChecklistStep[]; doneCount: number }>();
const emit = defineEmits<{ dismiss: [] }>();
const { t } = useI18n();

// The dismiss result (success or failure) is toasted by useOnboardingChecklist's
// `dismiss()` itself, once the request actually resolves — this button only
// triggers it, it doesn't assume the outcome.
const onDismiss = () => {
  emit("dismiss");
};
</script>

<template>
  <section data-tour="checklist" class="mb-6 sm:mb-8">
    <Card padding="loose">
      <header class="mb-4 flex items-start justify-between gap-3">
        <div>
          <h2 class="text-card-title font-semibold text-ink">{{ t("owner.checklist.title") }}</h2>
          <p class="mt-1 text-caption text-ink-muted">
            {{ t("owner.checklist.progress", { done: doneCount, total: steps.length }) }}
          </p>
        </div>
        <button
          type="button"
          class="rounded-sm p-1 text-ink-faint outline-none transition hover:text-ink focus-visible:shadow-focus"
          :aria-label="t('owner.checklist.dismiss')"
          @click="onDismiss"
        >
          <Icon name="X" :size="16" />
        </button>
      </header>

      <ol class="divide-y divide-line-passive">
        <li v-for="(step, i) in steps" :key="step.key">
          <component
            :is="step.enabled && !step.done ? 'NuxtLink' : 'div'"
            :to="step.enabled && !step.done ? step.to : undefined"
            class="group flex items-start gap-3 rounded-sm py-3 outline-none transition"
            :class="step.enabled && !step.done ? 'hover:bg-surface-hover focus-visible:shadow-focus' : ''"
            :aria-disabled="!step.enabled"
          >
            <span
              class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-pill text-micro font-semibold"
              :class="step.done
                ? 'bg-status-paid-soft text-status-paid'
                : step.enabled ? 'bg-ink text-surface-page' : 'bg-line-passive text-ink-faint'"
            >
              <Icon v-if="step.done" name="Check" :size="12" />
              <template v-else>{{ i + 1 }}</template>
            </span>
            <div class="min-w-0 flex-1">
              <p
                class="text-body font-medium"
                :class="step.done ? 'text-ink-muted line-through' : step.enabled ? 'text-ink' : 'text-ink-faint'"
              >
                {{ t(`owner.checklist.steps.${step.key}.title`) }}
              </p>
              <p v-if="!step.done" class="text-caption" :class="step.enabled ? 'text-ink-muted' : 'text-ink-faint'">
                {{ t(`owner.checklist.steps.${step.key}.hint`) }}
              </p>
            </div>
            <Icon
              v-if="step.enabled && !step.done"
              name="ArrowRight"
              :size="14"
              class="mt-1 shrink-0 text-ink-faint transition group-hover:text-ink-muted"
            />
          </component>
        </li>
      </ol>
    </Card>
  </section>
</template>
