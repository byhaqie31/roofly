<script setup lang="ts">
import { computed, ref } from "vue";
import { Sparkles } from "lucide-vue-next";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";
import PasswordInput from "~/components/ui/PasswordInput.vue";
import GoogleSignInButton from "~/components/auth/GoogleSignInButton.vue";

definePageMeta({ layout: "auth" });

const { t } = useI18n();
useHead({ title: () => t("auth.register") });

const { toFieldErrors } = useApiError();
const auth = useAuthStore();
const env = useEnv();
const { features, isDemo } = env;
const { track, visitorId } = useTrack();
const name = ref("");
// The waitlist invitation email links here with ?email= so the invitee doesn't retype it.
const invitedEmail = useRoute().query.email;
const email = ref(typeof invitedEmail === "string" ? invitedEmail : "");
const phone = ref("");
const password = ref("");
const passwordConfirmation = ref("");
const submitted = ref(false);
const error = ref<string | null>(null);

// Live once the user has tried to submit, so they see it clear as they fix it.
const confirmError = computed(() =>
  submitted.value && passwordConfirmation.value !== password.value
    ? t("auth.passwordMismatch")
    : undefined,
);

const onSubmit = async () => {
  error.value = null;
  submitted.value = true;
  if (!name.value || !email.value || !phone.value || !password.value || !passwordConfirmation.value) {
    error.value = t("validation.required");
    return;
  }
  if (password.value.length < 8) {
    error.value = t("validation.minLength", { min: 8 });
    return;
  }
  if (confirmError.value) return;
  try {
    await auth.register({
      name: name.value,
      email: email.value,
      phone: phone.value,
      password: password.value,
      passwordConfirmation: passwordConfirmation.value,
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

// Demo has no real Google client — stand in with the same fresh Google-owner
// account the login page's demo shortcut uses (lands on owner onboarding).
const demoGoogleLoading = ref(false);
const onDemoGoogle = async () => {
  demoGoogleLoading.value = true;
  try {
    await auth.loginWithGoogle("demo");
    await navigateTo("/owner");
  } finally {
    demoGoogleLoading.value = false;
  }
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

    <div v-if="features.googleLogin || isDemo" class="mb-6 space-y-3">
      <GoogleSignInButton v-if="features.googleLogin" @credential="onGoogle" />
      <Button
        v-else
        variant="ghost"
        size="lg"
        block
        :loading="demoGoogleLoading"
        @click="onDemoGoogle"
      >
        <Sparkles :size="16" :stroke-width="1.5" />
        {{ t("demo.shortcuts.continueWithGoogle") }}
      </Button>
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
        :placeholder="t('auth.placeholders.fullName')"
        size="lg"
      />
      <Input
        v-model="email"
        type="email"
        autocomplete="email"
        :label="t('auth.email')"
        :placeholder="t('auth.placeholders.email')"
        size="lg"
      />
      <Input
        v-model="phone"
        type="tel"
        autocomplete="tel"
        :label="t('auth.phone')"
        :placeholder="t('auth.placeholders.phone')"
        size="lg"
      />
      <PasswordInput
        v-model="password"
        autocomplete="new-password"
        :label="t('auth.password')"
        :placeholder="t('auth.placeholders.newPassword')"
        size="lg"
      />
      <PasswordInput
        v-model="passwordConfirmation"
        autocomplete="new-password"
        :label="t('auth.confirmPassword')"
        :placeholder="t('auth.placeholders.confirmPassword')"
        :error="confirmError"
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
