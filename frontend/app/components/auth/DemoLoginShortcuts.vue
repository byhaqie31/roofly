<script setup lang="ts">
import { Building2, DoorOpen } from "lucide-vue-next";
import Button from "~/components/ui/Button.vue";

/**
 * POC demo shortcuts — skip the login form and land directly in either
 * dashboard. Auth is mocked in Phase 1, so the email prefix decides role
 * (see app/stores/auth.ts).
 *
 * Visible only when import.meta.dev is true. Once the real backend lands,
 * this stays useful: the seeded accounts are owner@roofly.my / tenant@roofly.my
 * with the same password, so the shortcuts will hit the real /api/auth/login.
 */

// Flip to true once the tenant shell is ready to demo. The button's
// click handler and loading state are already wired — only the visual
// gate below switches presentations.
const TENANT_ENABLED = false;

const auth = useAuthStore();
const { t } = useI18n();
const loadingRole = ref<"owner" | "tenant" | null>(null);
const { showDemoShortcuts } = useEnv();
const showShortcuts = import.meta.dev || showDemoShortcuts;

const enter = async (role: "owner" | "tenant") => {
  loadingRole.value = role;
  await auth.login(`${role}@roofly.my`, "password");
  await navigateTo(role === "owner" ? "/owner" : "/tenant");
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
    </div>
  </section>
</template>
