<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";
import SiteFooter from "~/components/layout/SiteFooter.vue";
import { LEGAL } from "~/config/legal";

definePageMeta({ layout: false });
const { t } = useI18n();
useHead({ title: () => t("suspended.title") });

const auth = useAuthStore();
const onLogout = async () => {
  await auth.logout();
  await navigateTo("/auth/login");
};
</script>

<template>
  <div class="min-h-dvh bg-surface-page text-ink flex flex-col">
    <div class="flex-1 flex items-center justify-center px-6 py-10">
      <Card padding="loose" class="w-full max-w-auth-card text-center">
        <Icon name="ShieldOff" :size="40" class="mx-auto text-ink-faint" />
        <h1 class="mt-4 text-display-sub font-semibold tracking-snug">{{ t("suspended.title") }}</h1>
        <p class="mt-3 text-body text-ink-muted">{{ t("suspended.body") }}</p>
        <!-- Contact email from config/legal.ts (roofly.my has no mailbox). -->
        <a
          v-if="LEGAL.contact.email"
          :href="`mailto:${LEGAL.contact.email}`"
          class="mt-6 inline-block text-body text-ink underline underline-offset-2"
        >
          {{ t("suspended.contact") }}
        </a>
        <div class="mt-8">
          <Button variant="ghost" @click="onLogout">{{ t("auth.logout") }}</Button>
        </div>
      </Card>
    </div>
    <SiteFooter tone="theme" variant="slim" />
  </div>
</template>
