<script setup lang="ts">
import { ref } from "vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";

definePageMeta({ layout: "auth" });

const { t } = useI18n();
useHead({ title: () => t("auth.register") });

const auth = useAuthStore();
const name = ref("");
const email = ref("");
const phone = ref("");
const password = ref("");
const error = ref<string | null>(null);

const onSubmit = async () => {
  error.value = null;
  if (!name.value || !email.value || !phone.value || !password.value) {
    error.value = t("validation.required");
    return;
  }
  if (password.value.length < 8) {
    error.value = t("validation.minLength", { min: 8 });
    return;
  }
  await auth.register({
    name: name.value,
    email: email.value,
    phone: phone.value,
    password: password.value,
  });
  await navigateTo("/owner");
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("auth.registerTitle") }}
      </h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("auth.registerSubtitle") }}</p>
    </header>

    <form class="space-y-4" @submit.prevent="onSubmit">
      <Input
        v-model="name"
        autocomplete="name"
        :label="t('auth.fullName')"
        size="lg"
      />
      <Input
        v-model="email"
        type="email"
        autocomplete="email"
        :label="t('auth.email')"
        size="lg"
      />
      <Input
        v-model="phone"
        type="tel"
        autocomplete="tel"
        placeholder="+60 12 345 6789"
        :label="t('auth.phone')"
        size="lg"
      />
      <Input
        v-model="password"
        type="password"
        autocomplete="new-password"
        :label="t('auth.password')"
        size="lg"
      />

      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>

      <Button type="submit" variant="primary" size="lg" :loading="auth.loading" block>
        {{ t("auth.signupAsOwner") }}
      </Button>
    </form>

    <p class="mt-6 text-center text-caption text-ink-muted">
      {{ t("auth.haveAccount") }}
      <NuxtLink to="/auth/login" class="text-ink underline underline-offset-2">
        {{ t("auth.login") }}
      </NuxtLink>
    </p>
  </div>
</template>
