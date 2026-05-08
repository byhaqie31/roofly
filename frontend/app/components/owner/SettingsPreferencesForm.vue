<script setup lang="ts">
import { ref } from "vue";
import type { Locale, OwnerAccount, ThemePreference } from "~/types/owner";
import { useToast } from "~/composables/useToast";
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{ account: OwnerAccount }>();
const emit = defineEmits<{ saved: [account: OwnerAccount] }>();

const { t, setLocale } = useI18n();
const { setTheme } = useTheme();
const { show } = useToast();
const submitting = ref(false);

const localeChoice = ref<Locale>(props.account.preferences.locale);
const themeChoice = ref<ThemePreference>(props.account.preferences.theme);

const localeOptions: { value: Locale; label: string; help: string }[] = [
  { value: "en", label: "English", help: "English" },
  { value: "ms", label: "Bahasa Melayu", help: "Bahasa Melayu" },
];

const themeOptions: { value: ThemePreference; labelKey: string; helpKey: string }[] = [
  {
    value: "light",
    labelKey: "common.themeLight",
    helpKey: "owner.settings.preferences.themeLightHelp",
  },
  {
    value: "dark",
    labelKey: "common.themeDark",
    helpKey: "owner.settings.preferences.themeDarkHelp",
  },
  {
    value: "system",
    labelKey: "common.themeSystem",
    helpKey: "owner.settings.preferences.themeSystemHelp",
  },
];

const onSubmit = async () => {
  submitting.value = true;
  try {
    // Apply the cookie-backed preferences immediately so the live UI reflects them.
    setTheme(themeChoice.value);
    await setLocale(localeChoice.value);
    const updated = await useOwnerSettings().updatePreferences({
      locale: localeChoice.value,
      theme: themeChoice.value,
    });
    emit("saved", updated);
    show(t("common.savedToast"), "success");
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <form class="space-y-6" @submit.prevent="onSubmit">
    <Card padding="loose" tone="flat">
      <header class="mb-4">
        <h2 class="text-card-title font-semibold text-ink">
          {{ t("common.language") }}
        </h2>
        <p class="mt-1 text-caption text-ink-muted">
          {{ t("owner.settings.preferences.languageHelp") }}
        </p>
      </header>

      <div class="space-y-2">
        <label
          v-for="opt in localeOptions"
          :key="opt.value"
          class="flex cursor-pointer items-center gap-3 rounded-md border border-line-passive p-3 transition hover:border-line-interactive"
          :class="
            localeChoice === opt.value
              ? 'border-line-interactive bg-surface-page'
              : ''
          "
        >
          <input
            v-model="localeChoice"
            type="radio"
            :value="opt.value"
            class="h-4 w-4 accent-ink"
          />
          <span class="text-body text-ink">{{ opt.label }}</span>
        </label>
      </div>
    </Card>

    <Card padding="loose" tone="flat">
      <header class="mb-4">
        <h2 class="text-card-title font-semibold text-ink">
          {{ t("common.theme") }}
        </h2>
        <p class="mt-1 text-caption text-ink-muted">
          {{ t("owner.settings.preferences.themeHelp") }}
        </p>
      </header>

      <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
        <label
          v-for="opt in themeOptions"
          :key="opt.value"
          class="flex cursor-pointer flex-col gap-1 rounded-md border border-line-passive p-3 transition hover:border-line-interactive"
          :class="
            themeChoice === opt.value
              ? 'border-line-interactive bg-surface-page'
              : ''
          "
        >
          <span class="flex items-center gap-2">
            <input
              v-model="themeChoice"
              type="radio"
              :value="opt.value"
              class="h-4 w-4 accent-ink"
            />
            <span class="text-body text-ink">{{ t(opt.labelKey) }}</span>
          </span>
          <span class="ml-6 text-micro text-ink-faint">
            {{ t(opt.helpKey) }}
          </span>
        </label>
      </div>
    </Card>

    <div class="flex justify-end">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.settings.save") }}
      </Button>
    </div>
  </form>
</template>
