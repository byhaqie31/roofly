<script setup lang="ts">
import { Building2, DoorOpen } from "lucide-vue-next";
import Button from "~/components/ui/Button.vue";

/**
 * POC demo shortcuts — skip the login form and land directly in either
 * dashboard. Auth is mocked in Phase 1, so the email prefix decides role
 * (see app/stores/auth.ts).
 *
 * Visible in local dev (import.meta.dev) and on the demo deployment
 * (NUXT_PUBLIC_DEMO_MODE=true). Hidden on real prod.
 */
const auth = useAuthStore();
const loadingRole = ref<"owner" | "tenant" | null>(null);
const config = useRuntimeConfig();
const showShortcuts = import.meta.dev || config.public.demoMode;

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
    aria-label="Demo shortcuts"
  >
    <div class="flex items-center justify-between mb-3">
      <p class="text-micro font-medium uppercase tracking-wider text-ink-muted">
        Demo shortcuts
      </p>
      <span class="text-micro text-ink-faint">Demo only</span>
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
        Continue as owner
      </Button>
      <Button
        variant="ghost"
        size="sm"
        :loading="loadingRole === 'tenant'"
        :disabled="loadingRole !== null"
        @click="enter('tenant')"
      >
        <DoorOpen :size="16" :stroke-width="1.5" />
        Continue as tenant
      </Button>
    </div>
  </section>
</template>
