<script setup lang="ts">
import { computed, ref } from "vue";
import {
  PopoverRoot,
  PopoverTrigger,
  PopoverPortal,
  PopoverContent,
} from "reka-ui";
import Icon from "~/components/ui/Icon.vue";

const props = defineProps<{
  month: string; // "01"-"12" or "all"
  year: string; // e.g. "2026" or "all"
  years: string[]; // available years (already sorted desc)
}>();

const emit = defineEmits<{
  "update:month": [value: string];
  "update:year": [value: string];
}>();

const { t } = useI18n();
const open = ref(false);

const monthKeys = [
  "jan", "feb", "mar", "apr", "may", "jun",
  "jul", "aug", "sep", "oct", "nov", "dec",
];

const months = computed(() =>
  monthKeys.map((key, idx) => ({
    value: (idx + 1).toString().padStart(2, "0"),
    label: t(`common.months.${key}`),
    short: t(`common.months.${key}`).slice(0, 3),
  })),
);

const triggerLabel = computed(() => {
  if (props.month === "all" && props.year === "all") {
    return t("owner.payments.filters.allPeriods");
  }
  const m =
    props.month === "all"
      ? t("owner.payments.filters.allMonths")
      : (months.value.find((mm) => mm.value === props.month)?.short ?? "");
  const y =
    props.year === "all" ? t("owner.payments.filters.allYears") : props.year;
  return `${m} · ${y}`;
});

const isActive = computed(
  () => props.month !== "all" || props.year !== "all",
);

const onPickMonth = (value: string) => emit("update:month", value);
const onPickYear = (value: string) => emit("update:year", value);
const onClear = () => {
  emit("update:month", "all");
  emit("update:year", "all");
};
</script>

<template>
  <PopoverRoot v-model:open="open">
    <PopoverTrigger as-child>
      <button
        type="button"
        :class="[
          'inline-flex h-10 items-center rounded-sm border outline-none transition focus-visible:shadow-focus',
          isActive
            ? 'gap-2 border-line-interactive bg-surface-raised px-3 text-ink'
            : 'w-10 justify-center border-line-passive bg-surface-page text-ink-muted hover:text-ink sm:w-auto sm:justify-start sm:gap-2 sm:px-3',
        ]"
        :aria-label="
          isActive
            ? t('owner.payments.filters.periodAriaActive', { label: triggerLabel })
            : t('owner.payments.filters.periodAria')
        "
      >
        <Icon name="Calendar" :size="16" />
        <span
          :class="[
            'text-caption tabular-nums',
            isActive ? 'inline' : 'hidden sm:inline',
          ]"
        >
          {{ triggerLabel }}
        </span>
        <Icon
          name="ChevronDown"
          :size="14"
          :class="[
            'text-ink-muted',
            isActive ? 'inline' : 'hidden sm:inline',
          ]"
        />
      </button>
    </PopoverTrigger>
    <PopoverPortal>
      <PopoverContent
        :side-offset="6"
        align="end"
        class="z-50 w-[20rem] rounded-md border border-line-passive bg-surface-raised shadow-modal outline-none"
      >
        <div class="flex items-center justify-between border-b border-line-passive px-4 py-3">
          <span class="text-caption font-semibold text-ink-strong">
            {{ t("owner.payments.filters.period") }}
          </span>
          <button
            v-if="isActive"
            type="button"
            class="text-caption text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus"
            @click="onClear"
          >
            {{ t("owner.payments.filters.clear") }}
          </button>
        </div>

        <div class="grid grid-cols-2 divide-x divide-line-passive">
          <div class="flex flex-col">
            <div class="px-4 pt-3 pb-2 text-micro font-semibold uppercase tracking-wide text-ink-muted">
              {{ t("owner.payments.filters.year") }}
            </div>
            <ul class="max-h-64 overflow-y-auto px-2 pb-2">
              <li>
                <button
                  type="button"
                  :class="[
                    'block w-full rounded-xs px-2 py-1.5 text-left text-body outline-none transition focus-visible:shadow-focus',
                    year === 'all'
                      ? 'bg-ink text-surface-ondark'
                      : 'text-ink hover:bg-[rgba(28,28,28,0.04)]',
                  ]"
                  @click="onPickYear('all')"
                >
                  {{ t("owner.payments.filters.allYears") }}
                </button>
              </li>
              <li v-for="y in years" :key="y">
                <button
                  type="button"
                  :class="[
                    'block w-full rounded-xs px-2 py-1.5 text-left text-body tabular-nums outline-none transition focus-visible:shadow-focus',
                    year === y
                      ? 'bg-ink text-surface-ondark'
                      : 'text-ink hover:bg-[rgba(28,28,28,0.04)]',
                  ]"
                  @click="onPickYear(y)"
                >
                  {{ y }}
                </button>
              </li>
            </ul>
          </div>

          <div class="flex flex-col">
            <div class="px-4 pt-3 pb-2 text-micro font-semibold uppercase tracking-wide text-ink-muted">
              {{ t("owner.payments.filters.month") }}
            </div>
            <ul class="max-h-64 overflow-y-auto px-2 pb-2">
              <li>
                <button
                  type="button"
                  :class="[
                    'block w-full rounded-xs px-2 py-1.5 text-left text-body outline-none transition focus-visible:shadow-focus',
                    month === 'all'
                      ? 'bg-ink text-surface-ondark'
                      : 'text-ink hover:bg-[rgba(28,28,28,0.04)]',
                  ]"
                  @click="onPickMonth('all')"
                >
                  {{ t("owner.payments.filters.allMonths") }}
                </button>
              </li>
              <li v-for="m in months" :key="m.value">
                <button
                  type="button"
                  :class="[
                    'block w-full rounded-xs px-2 py-1.5 text-left text-body outline-none transition focus-visible:shadow-focus',
                    month === m.value
                      ? 'bg-ink text-surface-ondark'
                      : 'text-ink hover:bg-[rgba(28,28,28,0.04)]',
                  ]"
                  @click="onPickMonth(m.value)"
                >
                  {{ m.label }}
                </button>
              </li>
            </ul>
          </div>
        </div>
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>
