<script setup lang="ts">
import { ref } from "vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";

definePageMeta({ layout: "auth" });

const { t } = useI18n();
useHead({ title: () => t("auth.login") });

const auth = useAuthStore();
const email = ref("");
const password = ref("");
const error = ref<string | null>(null);

const onSubmit = async () => {
  error.value = null;
  if (!email.value || !password.value) {
    error.value = t("validation.required");
    return;
  }
  await auth.login(email.value, password.value);
  if (auth.isTenant) {
    await navigateTo("/tenant");
  } else {
    await navigateTo("/owner");
  }
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("auth.loginTitle") }}
      </h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("auth.loginSubtitle") }}</p>
    </header>

    <form class="space-y-4" @submit.prevent="onSubmit">
      <Input
        v-model="email"
        type="email"
        autocomplete="email"
        :label="t('auth.email')"
        size="lg"
      />
      <Input
        v-model="password"
        type="password"
        autocomplete="current-password"
        :label="t('auth.password')"
        size="lg"
      />

      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>

      <Button type="submit" variant="primary" size="lg" :loading="auth.loading" block>
        {{ t("auth.login") }}
      </Button>
    </form>

    <p class="mt-6 text-center text-caption text-ink-muted">
      {{ t("auth.noAccount") }}
      <NuxtLink to="/auth/register" class="text-ink underline underline-offset-2">
        {{ t("auth.signupAsOwner") }}
      </NuxtLink>
    </p>
  </div>
</template>
