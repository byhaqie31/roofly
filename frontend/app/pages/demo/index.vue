<script setup lang="ts">
import { Building2, DoorOpen } from "lucide-vue-next";
import Button from "~/components/ui/Button.vue";

definePageMeta({ layout: "auth" });

const { t } = useI18n();
useHead({ title: () => "Demo · Roofly.my" });

// Tenant shell isn't ready yet — gate the tenant button visually but keep
// its handler wired so it's a one-character flip when the shell lands.
const TENANT_ENABLED = false;

const auth = useAuthStore();
const loadingRole = ref<"owner" | "tenant" | null>(null);

const enter = async (role: "owner" | "tenant") => {
  loadingRole.value = role;
  await auth.login(`${role}@roofly.my`, "password");
  await navigateTo(role === "owner" ? "/owner" : "/tenant");
  loadingRole.value = null;
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <p class="text-micro font-medium uppercase tracking-wider text-ink-muted mb-3">
        Demo environment
      </p>
      <h1 class="text-display-sub font-semibold tracking-snug">
        Welcome to the Roofly demo
      </h1>
      <p class="mt-2 text-body text-ink-muted">
        Pick a role to start exploring. All data shown is sample data — nothing
        you do here is saved.
      </p>
    </header>

    <div class="space-y-3">
      <Button
        variant="primary"
        size="lg"
        block
        :loading="loadingRole === 'owner'"
        :disabled="loadingRole !== null"
        @click="enter('owner')"
      >
        <Building2 :size="18" :stroke-width="1.5" />
        Continue as owner
      </Button>

      <Button
        v-if="TENANT_ENABLED"
        variant="ghost"
        size="lg"
        block
        :loading="loadingRole === 'tenant'"
        :disabled="loadingRole !== null"
        @click="enter('tenant')"
      >
        <DoorOpen :size="18" :stroke-width="1.5" />
        Continue as tenant
      </Button>

      <button
        v-else
        type="button"
        aria-disabled="true"
        aria-label="Tenant demo — coming soon"
        tabindex="-1"
        class="w-full flex flex-col items-center justify-center gap-1 rounded-sm border border-dashed border-line-passive bg-transparent px-4 py-3 text-body text-ink-muted outline-none cursor-not-allowed"
      >
        <span class="inline-flex items-center gap-2">
          <DoorOpen :size="18" :stroke-width="1.5" />
          Continue as tenant
        </span>
        <span class="text-micro text-ink-faint">Coming soon</span>
      </button>
    </div>

    <p class="mt-8 text-center text-caption text-ink-muted">
      Want the real thing? Visit
      <a
        href="https://roofly.my"
        class="text-ink underline underline-offset-2"
      >
        roofly.my
      </a>
      when we launch.
    </p>
  </div>
</template>
