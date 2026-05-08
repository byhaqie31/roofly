<script setup lang="ts">
import { Sun, Moon, Monitor } from "lucide-vue-next";
import { computed } from "vue";

const { t } = useI18n();
const { theme, setTheme } = useTheme();

const Icon = computed(() => {
  if (theme.value === "dark") return Moon;
  if (theme.value === "light") return Sun;
  return Monitor;
});

const label = computed(() => {
  if (theme.value === "dark") return t("common.themeDark");
  if (theme.value === "light") return t("common.themeLight");
  return t("common.themeSystem");
});

const cycle = () => {
  const order = ["light", "dark", "system"] as const;
  const idx = order.indexOf(theme.value);
  setTheme(order[(idx + 1) % order.length]!);
};
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center gap-1.5 h-9 px-2.5 rounded-sm text-ink-strong text-caption hover:bg-[rgba(28,28,28,0.04)] focus-visible:shadow-focus transition"
    :aria-label="$t('common.theme')"
    @click="cycle"
  >
    <component :is="Icon" :size="16" :stroke-width="1.5" />
    <span class="font-medium">{{ label }}</span>
  </button>
</template>
