<script setup lang="ts">
import { Languages } from "lucide-vue-next";

const { locale, locales, setLocale } = useI18n();

const toggle = async () => {
  const next = locale.value === "en" ? "ms" : "en";
  await setLocale(next);
};

const label = computed(() => (locale.value === "en" ? "BM" : "EN"));
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center gap-1.5 h-9 px-2.5 rounded-sm text-ink-strong text-caption hover:bg-[rgba(28,28,28,0.04)] focus-visible:shadow-focus transition"
    :aria-label="$t('common.language')"
    @click="toggle"
  >
    <Languages :size="16" :stroke-width="1.5" />
    <span class="font-medium">{{ label }}</span>
  </button>
</template>
