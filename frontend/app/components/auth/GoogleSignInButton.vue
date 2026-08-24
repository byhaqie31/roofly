<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { getGis, loadGisScript } from "~/utils/gisLoader";

/**
 * Renders Google Identity Services' own button and emits the ID token.
 * Loads the GIS script lazily; if it can't load (blocked, offline, or just
 * never fires an event) emits `unavailable` so the page can show a muted
 * fallback line instead of hanging on an empty div forever.
 *
 * The actual script-loading + terminal-state tracking lives in
 * `~/utils/gisLoader.ts`, a plain module — NOT declared here in
 * `<script setup>`. Top-level `let`/`const` bindings in a `<script setup>`
 * block are compiled into this component's `setup()` function, so they are
 * re-initialised on every new instance (every mount): they cannot give the
 * "a second mount reads the first mount's failure synchronously" behaviour
 * this component depends on. `gisLoader.ts`'s top-level bindings are
 * evaluated exactly once, the first time the module is imported, and every
 * mount of this component — on /auth/login, on /auth/register, or a remount
 * after navigating between them — imports and shares that one evaluation.
 */
const emit = defineEmits<{ credential: [token: string]; unavailable: [] }>();

const { googleClientId } = useEnv();
const { locale } = useI18n();

// useTheme() only returns `theme`, `setTheme`, `initTheme` — it does not
// expose the resolved light/dark value. `theme` is the raw cookie value
// ("light" | "dark" | "system"); for "system" we read the same shared
// `roofly-system-dark` useState that useTheme.ts's own `resolved` computed
// derives from (kept in sync by the `initTheme()` matchMedia listener
// wired up in plugins/theme.ts) rather than re-deriving it independently.
const { theme } = useTheme();
const systemDark = useState<boolean | null>("roofly-system-dark");
const isDark = computed(() => {
  if (theme.value === "light") return false;
  if (theme.value === "dark") return true;
  return systemDark.value ?? false;
});

const host = ref<HTMLDivElement | null>(null);
const failed = ref(false);

// Guards the async work in onMounted from acting after this instance has
// been torn down (e.g. navigating from /auth/login to /auth/register while
// the script is still loading) — see onBeforeUnmount below. This one is
// correctly instance-scoped: each mount must only act on its own outcome.
let alive = true;

const render = () => {
  const id = getGis();
  if (!id || !host.value) return;
  host.value.innerHTML = "";
  id.renderButton(host.value, {
    type: "standard",
    theme: isDark.value ? "filled_black" : "outline",
    size: "large",
    text: "continue_with",
    shape: "rectangular",
    width: host.value.clientWidth || 320,
    locale: locale.value,
  });
};

onMounted(async () => {
  try {
    await loadGisScript();
    if (!alive) return;
    getGis()!.initialize({
      client_id: googleClientId,
      ux_mode: "popup",
      callback: (r) => emit("credential", r.credential),
    });
    render();
  } catch {
    if (!alive) return;
    failed.value = true;
    emit("unavailable");
  }
});

onBeforeUnmount(() => {
  alive = false;
});

watch([locale, isDark], () => render());
</script>

<template>
  <div class="w-full">
    <div v-if="!failed" ref="host" class="flex w-full justify-center" />
    <p v-else class="text-center text-caption text-ink-muted">
      {{ $t("auth.google.unavailable") }}
    </p>
  </div>
</template>
