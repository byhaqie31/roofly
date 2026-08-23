<script setup lang="ts">
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";

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
  <div class="min-h-dvh bg-surface-page text-ink flex items-center justify-center px-6">
    <Card padding="loose" class="w-full max-w-auth-card text-center">
      <Icon name="ShieldOff" :size="40" class="mx-auto text-ink-faint" />
      <h1 class="mt-4 text-display-sub font-semibold tracking-snug">{{ t("suspended.title") }}</h1>
      <p class="mt-3 text-body text-ink-muted">{{ t("suspended.body") }}</p>
      <a
        href="mailto:support@roofly.my"
        class="mt-6 inline-block text-body text-ink underline underline-offset-2"
      >
        {{ t("suspended.contact") }}
      </a>
      <div class="mt-8">
        <Button variant="ghost" @click="onLogout">{{ t("auth.logout") }}</Button>
      </div>
    </Card>
  </div>
</template>
