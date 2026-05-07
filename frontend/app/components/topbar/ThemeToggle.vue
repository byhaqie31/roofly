<script setup lang="ts">
import { Sun, Moon, Monitor } from "lucide-vue-next";
import { ref, computed } from "vue";

const { theme, setTheme } = useTheme();
const open = ref(false);

const Icon = computed(() => {
  if (theme.value === "dark") return Moon;
  if (theme.value === "light") return Sun;
  return Monitor;
});

const cycle = () => {
  const order: Array<"light" | "dark" | "system"> = ["light", "dark", "system"];
  const idx = order.indexOf(theme.value);
  setTheme(order[(idx + 1) % order.length]);
};
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center justify-center w-9 h-9 rounded-sm text-ink-strong hover:bg-[rgba(28,28,28,0.04)] focus-visible:shadow-focus transition"
    :aria-label="$t('common.theme')"
    @click="cycle"
  >
    <component :is="Icon" :size="18" :stroke-width="1.5" />
  </button>
</template>
