<script setup lang="ts">
import { ref } from "vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";
import GoogleSignInButton from "~/components/auth/GoogleSignInButton.vue";

definePageMeta({ layout: "auth" });

const { t } = useI18n();
useHead({ title: () => t("auth.register") });

const { toFieldErrors } = useApiError();
const auth = useAuthStore();
const env = useEnv();
const { features } = env;
const { track, visitorId } = useTrack();
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
  try {
    await auth.register({
      name: name.value,
      email: email.value,
      phone: phone.value,
      password: password.value,
      visitorId: env.trackingEnabled ? visitorId() : undefined,
    });
    track("register", { email: email.value, userId: auth.user?.id ?? "" });
    await navigateTo("/owner");
  } catch (err) {
    const fieldErrors = toFieldErrors(err);
    error.value = fieldErrors ? Object.values(fieldErrors)[0]! : t("auth.invalidCredentials");
  }
};

const { googleError, onGoogle } = useGoogleSignIn(async () => {
  await navigateTo("/owner");
});
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("auth.registerTitle") }}
      </h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("auth.registerSubtitle") }}</p>
    </header>

    <div v-if="features.googleLogin" class="mb-6 space-y-3">
      <GoogleSignInButton @credential="onGoogle" />
      <p v-if="googleError" class="text-center text-caption text-accent" role="alert">{{ googleError }}</p>
      <div class="flex items-center gap-3 text-micro uppercase tracking-wider text-ink-faint">
        <span class="h-px flex-1 bg-line-passive" />
        {{ t("auth.google.or") }}
        <span class="h-px flex-1 bg-line-passive" />
      </div>
    </div>

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
