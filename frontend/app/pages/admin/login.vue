<script setup lang="ts">
import { ref } from "vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";

definePageMeta({ layout: "auth-admin" });

const { t } = useI18n();
useHead({ title: () => t("auth.admin.title") });

const auth = useAuthStore();
const email = ref("");
const password = ref("");
const error = ref<string | null>(null);

// Already signed in as an admin? Skip the form.
if (import.meta.client && auth.isAdmin) {
  await navigateTo("/admin");
}

const onSubmit = async () => {
  error.value = null;
  if (!email.value || !password.value) {
    error.value = t("validation.required");
    return;
  }
  try {
    await auth.loginAdmin(email.value, password.value);
    await navigateTo("/admin");
  } catch {
    error.value = t("auth.invalidCredentials");
  }
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("auth.admin.title") }}</h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("auth.admin.subtitle") }}</p>
    </header>

    <form class="space-y-4" @submit.prevent="onSubmit">
      <Input v-model="email" type="email" autocomplete="email" :label="t('auth.email')" size="lg" />
      <Input v-model="password" type="password" autocomplete="current-password" :label="t('auth.password')" size="lg" />
      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
      <Button type="submit" variant="primary" size="lg" :loading="auth.loading" block>
        {{ t("auth.admin.login") }}
      </Button>
    </form>

    <p class="mt-6 text-center text-caption text-ink-muted">
      {{ t("auth.admin.notCustomer") }}
      <NuxtLink to="/auth/login" class="text-ink underline underline-offset-2">{{ t("auth.admin.customerLogin") }}</NuxtLink>
    </p>
  </div>
</template>
