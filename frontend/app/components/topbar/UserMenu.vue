<script setup lang="ts">
import { LogOut, Sun, Moon, Monitor, Languages, User } from "lucide-vue-next";
import { computed, ref, watch } from "vue";

withDefaults(defineProps<{ showLocale?: boolean }>(), { showLocale: true });

const auth = useAuthStore();
const { t, locale, setLocale } = useI18n();
const { theme, setTheme } = useTheme();
const { isDemo } = useEnv();
const open = ref(false);

// In demo mode, "logout" reads as "exit demo" and returns to /demo so the
// next visitor lands on the curated demo entry, not the auth/login page.
const logoutLabel = computed(() =>
  isDemo ? t("auth.exitDemo") : t("auth.logout"),
);

const onLogout = async () => {
  const wasAdmin = auth.isAdmin;
  await auth.logout();
  await navigateTo(wasAdmin ? "/admin/login" : isDemo ? "/demo" : "/auth/login");
};

// The trigger shows the account photo when we have one (Google sign-in
// stores `avatarUrl`), otherwise a generic user glyph — never name initials.
const avatarUrl = computed(() => auth.user?.avatarUrl ?? null);
const avatarBroken = ref(false);
watch(avatarUrl, () => {
  avatarBroken.value = false;
});
const showPhoto = computed(() => Boolean(avatarUrl.value) && !avatarBroken.value);

const ThemeIcon = computed(() => {
  if (theme.value === "dark") return Moon;
  if (theme.value === "light") return Sun;
  return Monitor;
});

const themeLabel = computed(() => {
  if (theme.value === "dark") return t("common.themeDark");
  if (theme.value === "light") return t("common.themeLight");
  return t("common.themeSystem");
});

const cycleTheme = () => {
  const order = ["light", "dark", "system"] as const;
  const idx = order.indexOf(theme.value);
  setTheme(order[(idx + 1) % order.length]!);
};

const localeLabel = computed(() =>
  locale.value === "en" ? "English" : "Bahasa Melayu",
);

const toggleLocale = async () => {
  await setLocale(locale.value === "en" ? "ms" : "en");
};
</script>

<template>
  <div class="relative">
    <button
      type="button"
      class="inline-flex shrink-0 items-center justify-center h-9 w-9 overflow-hidden rounded-pill bg-accent-soft text-accent hover:opacity-90 focus-visible:shadow-focus transition"
      :aria-label="auth.user?.name ?? 'User menu'"
      @click="open = !open"
    >
      <img
        v-if="showPhoto"
        :src="avatarUrl ?? undefined"
        alt=""
        class="h-full w-full object-cover"
        referrerpolicy="no-referrer"
        @error="avatarBroken = true"
      />
      <User v-else :size="18" :stroke-width="1.75" aria-hidden="true" />
    </button>
    <Transition name="fade">
      <div
        v-if="open"
        class="absolute right-0 mt-2 w-60 rounded-lg border border-line-passive bg-surface-raised shadow-modal p-2 z-50"
        @mouseleave="open = false"
      >
        <div class="px-3 py-2 border-b border-line-passive">
          <div class="text-caption font-medium text-ink truncate">{{ auth.user?.name }}</div>
          <div class="text-micro text-ink-muted truncate">{{ auth.user?.email }}</div>
        </div>

        <!-- Mobile-only: theme + language live in here on phones, while the
             topbar shows them inline on desktop. -->
        <div class="md:hidden border-b border-line-passive py-1 mt-1">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 px-3 py-2 rounded-sm text-caption text-ink-strong hover:bg-[rgba(28,28,28,0.04)] focus-visible:shadow-focus transition"
            @click="cycleTheme"
          >
            <span class="inline-flex items-center gap-2">
              <component :is="ThemeIcon" :size="16" :stroke-width="1.5" />
              {{ $t("common.theme") }}
            </span>
            <span class="text-micro text-ink-muted">{{ themeLabel }}</span>
          </button>
          <button
            type="button"
            class="flex w-full items-center justify-between gap-2 px-3 py-2 rounded-sm text-caption text-ink-strong hover:bg-[rgba(28,28,28,0.04)] focus-visible:shadow-focus transition"
            v-if="showLocale"
            @click="toggleLocale"
          >
            <span class="inline-flex items-center gap-2">
              <Languages :size="16" :stroke-width="1.5" />
              {{ $t("common.language") }}
            </span>
            <span class="text-micro text-ink-muted">{{ localeLabel }}</span>
          </button>
        </div>

        <button
          type="button"
          class="mt-1 flex items-center gap-2 w-full px-3 py-2 rounded-sm text-caption text-ink-strong hover:bg-[rgba(28,28,28,0.04)] focus-visible:shadow-focus transition"
          @click="onLogout"
        >
          <LogOut :size="16" :stroke-width="1.5" />
          {{ logoutLabel }}
        </button>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 120ms ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
