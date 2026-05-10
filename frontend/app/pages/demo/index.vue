<script setup lang="ts">
import { Building2, DoorOpen } from "lucide-vue-next";
import Button from "~/components/ui/Button.vue";

definePageMeta({ layout: "auth" });

const { t } = useI18n();
useHead({ title: () => t("demo.landing.pageTitle") });

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
        {{ t("demo.landing.eyebrow") }}
      </p>
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("demo.landing.title") }}
      </h1>
      <p class="mt-2 text-body text-ink-muted">
        {{ t("demo.landing.subtitle") }}
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
        {{ t("demo.shortcuts.continueAsOwner") }}
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
        {{ t("demo.shortcuts.continueAsTenant") }}
      </Button>

      <button
        v-else
        type="button"
        aria-disabled="true"
        :aria-label="`${t('demo.shortcuts.continueAsTenant')} — ${t('demo.shortcuts.comingSoon')}`"
        tabindex="-1"
        class="w-full flex flex-col items-center justify-center gap-1 rounded-sm border border-dashed border-line-passive bg-transparent px-4 py-3 text-body text-ink-muted outline-none cursor-not-allowed"
      >
        <span class="inline-flex items-center gap-2">
          <DoorOpen :size="18" :stroke-width="1.5" />
          {{ t("demo.shortcuts.continueAsTenant") }}
        </span>
        <span class="text-micro text-ink-faint">
          {{ t("demo.shortcuts.comingSoon") }}
        </span>
      </button>
    </div>

    <p class="mt-8 text-center text-caption text-ink-muted">
      {{ t("demo.landing.footerPrefix") }}
      <a
        href="https://roofly.my"
        class="text-ink underline underline-offset-2"
      >
        roofly.my
      </a>
      {{ t("demo.landing.footerSuffix") }}
    </p>
  </div>
</template>
