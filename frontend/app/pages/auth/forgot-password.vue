<script setup lang="ts">
import { ref } from "vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";

definePageMeta({ layout: "auth" });
const { t } = useI18n();
useHead({ title: () => t("auth.forgot.title") });

const auth = useAuthStore();
const email = ref("");
const error = ref<string | null>(null);
const sent = ref(false);

const onSubmit = async () => {
  error.value = null;
  if (!email.value) {
    error.value = t("validation.required");
    return;
  }
  try {
    await auth.forgotPassword(email.value);
    sent.value = true;
  } catch {
    error.value = t("common.genericError");
  }
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("auth.forgot.title") }}</h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("auth.forgot.subtitle") }}</p>
    </header>

    <div v-if="sent" class="rounded-lg border border-line-passive bg-surface-raised p-6 text-center">
      <p class="text-body text-ink">{{ t("auth.forgot.sentTitle") }}</p>
      <p class="mt-2 text-caption text-ink-muted">{{ t("auth.forgot.sentHelp", { email }) }}</p>
    </div>

    <form v-else class="space-y-4" @submit.prevent="onSubmit">
      <Input v-model="email" type="email" autocomplete="email" :label="t('auth.email')" size="lg" />
      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
      <Button type="submit" variant="primary" size="lg" :loading="auth.loading" block>
        {{ t("auth.forgot.submit") }}
      </Button>
    </form>

    <p class="mt-6 text-center text-caption text-ink-muted">
      <NuxtLink to="/auth/login" class="text-ink underline underline-offset-2">{{ t("auth.forgot.backToLogin") }}</NuxtLink>
    </p>
  </div>
</template>
