<script setup lang="ts">
import type * as lucideIcons from "lucide-vue-next";
import { OWNER_PURPOSES, type OwnerPurpose } from "~/types/auth";
import Icon from "~/components/ui/Icon.vue";

/**
 * Controlled multi-select of `OwnerPurpose`. No page-specific assumptions,
 * no service calls, no navigation — reused as-is by Settings → Preferences.
 */
const model = defineModel<OwnerPurpose[]>({ required: true });
/**
 * Optional id of an external heading that names this group for assistive
 * tech (e.g. a Settings section `<h2>`). Onboarding leaves this unset — an
 * `<h1>` sits directly beside the picker there, so `role="group"` alone is
 * unambiguous.
 */
const props = defineProps<{ labelledby?: string }>();
const { t } = useI18n();

const icons: Record<OwnerPurpose, keyof typeof lucideIcons> = {
  rental: "KeyRound",
  own_stay: "Home",
  investment: "TrendingUp",
};

const toggle = (p: OwnerPurpose) => {
  model.value = model.value.includes(p)
    ? model.value.filter((x) => x !== p)
    : [...model.value, p];
};
</script>

<template>
  <div
    class="grid grid-cols-[repeat(auto-fit,minmax(13rem,1fr))] gap-3"
    role="group"
    :aria-labelledby="props.labelledby"
  >
    <button
      v-for="p in OWNER_PURPOSES"
      :key="p"
      type="button"
      :aria-pressed="model.includes(p)"
      class="flex items-start gap-4 sm:flex-col sm:items-start sm:gap-3 rounded-lg border p-4 text-left outline-none transition focus-visible:shadow-focus"
      :class="model.includes(p)
        ? 'border-ink bg-surface-raised'
        : 'border-line-passive bg-surface-page hover:border-line-interactive'"
      @click="toggle(p)"
    >
      <span
        class="mt-0.5 sm:mt-0 flex h-9 w-9 shrink-0 items-center justify-center rounded-md"
        :class="model.includes(p) ? 'bg-ink text-surface-page' : 'bg-line-passive text-ink-muted'"
      >
        <Icon :name="icons[p]" :size="18" />
      </span>
      <span class="min-w-0">
        <span class="block text-body font-semibold text-ink">{{ t(`owner.purposes.${p}.title`) }}</span>
        <span class="mt-0.5 block text-caption text-ink-muted">{{ t(`owner.purposes.${p}.help`) }}</span>
      </span>
    </button>
  </div>
</template>
