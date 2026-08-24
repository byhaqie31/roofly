<script setup lang="ts">
import { ArrowUpRight, Building2, DoorOpen, Sparkles } from "lucide-vue-next";
import Button from "~/components/ui/Button.vue";

/**
 * POC demo shortcuts — skip the login form and land directly in either
 * dashboard. Auth is mocked in Phase 1, so the email prefix decides role
 * (see app/stores/auth.ts).
 *
 * Visible only on the demo environment (demo.roofly.my). Everywhere else —
 * including local dev against the API — the customer login shows a single
 * "Explore the demo" link pointing at demo.roofly.my instead. Anything demo
 * lives on that subdomain, never on the customer login.
 */

// Flip to true once the tenant shell is ready to demo. The button's
// click handler and loading state are already wired — only the visual
// gate below switches presentations.
const TENANT_ENABLED = true;

const auth = useAuthStore();
const { t } = useI18n();
const loadingRole = ref<"owner" | "tenant" | "google" | null>(null);
const { showDemoShortcuts } = useEnv();
const showShortcuts = showDemoShortcuts;
const { track } = useTrack();

const enter = async (role: "owner" | "tenant") => {
  track("demo_enter", { role });
  loadingRole.value = role;
  await auth.login(`${role}@roofly.my`, "password");
  await navigateTo(role === "owner" ? "/owner" : "/tenant");
  loadingRole.value = null;
};

const enterGoogle = async () => {
  track("demo_enter", { role: "owner_google" });
  loadingRole.value = "google";
  await auth.loginWithGoogle("demo");
  await navigateTo("/owner");
  loadingRole.value = null;
};
</script>

<template>
  <section
    v-if="showShortcuts"
    class="mt-8 pt-6 border-t border-line-passive"
    :aria-label="t('demo.shortcuts.label')"
  >
    <div class="flex items-center justify-between mb-3">
      <p class="text-micro font-medium uppercase tracking-wider text-ink-muted">
        {{ t("demo.shortcuts.label") }}
      </p>
      <span class="text-micro text-ink-faint">
        {{ t("demo.shortcuts.eyebrow") }}
      </span>
    </div>

    <div class="grid grid-cols-2 gap-2">
      <Button
        variant="ghost"
        size="sm"
        :loading="loadingRole === 'owner'"
        :disabled="loadingRole !== null"
        @click="enter('owner')"
      >
        <Building2 :size="16" :stroke-width="1.5" />
        {{ t("demo.shortcuts.continueAsOwner") }}
      </Button>

      <Button
        v-if="TENANT_ENABLED"
        variant="ghost"
        size="sm"
        :loading="loadingRole === 'tenant'"
        :disabled="loadingRole !== null"
        @click="enter('tenant')"
      >
        <DoorOpen :size="16" :stroke-width="1.5" />
        {{ t("demo.shortcuts.continueAsTenant") }}
      </Button>

      <button
        v-else
        type="button"
        aria-disabled="true"
        :aria-label="`${t('demo.shortcuts.continueAsTenant')} — ${t('demo.shortcuts.comingSoon')}`"
        tabindex="-1"
        class="flex flex-col items-center justify-center gap-0.5 rounded-sm border border-dashed border-line-passive bg-transparent px-3 py-1.5 text-caption text-ink-muted outline-none cursor-not-allowed transition"
      >
        <span class="inline-flex items-center gap-2">
          <DoorOpen :size="16" :stroke-width="1.5" />
          {{ t("demo.shortcuts.continueAsTenant") }}
        </span>
        <span class="text-micro text-ink-faint">
          {{ t("demo.shortcuts.comingSoon") }}
        </span>
      </button>

      <Button
        variant="ghost"
        size="sm"
        class="col-span-2"
        :loading="loadingRole === 'google'"
        :disabled="loadingRole !== null"
        @click="enterGoogle"
      >
        <Sparkles :size="16" :stroke-width="1.5" />
        {{ t("demo.shortcuts.continueWithGoogle") }}
      </Button>
    </div>
  </section>

  <p
    v-else
    class="mt-8 pt-6 border-t border-line-passive text-caption text-ink-muted text-center"
  >
    {{ t("demo.shortcuts.tryFirst") }}
    <a
      href="https://demo.roofly.my"
      target="_blank"
      rel="noopener noreferrer"
      class="inline-flex items-center gap-0.5 font-medium text-ink underline underline-offset-4 hover:text-accent"
    >
      {{ t("demo.shortcuts.exploreDemo") }}
      <ArrowUpRight :size="14" :stroke-width="1.75" />
    </a>
  </p>
</template>
