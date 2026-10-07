<script setup lang="ts">
import { computed, ref } from "vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";

/**
 * Tenant lands here from the invite email (spec 2026-10-07 § 4):
 * /auth/accept-invite?token=…&email=…  → sets a password → /tenant.
 * Mirrors reset-password.vue; the email is fixed by the link, not editable.
 */
definePageMeta({ layout: "auth" });
const { t } = useI18n();
useHead({ title: () => t("auth.acceptInvite.title") });

const route = useRoute();
const auth = useAuthStore();
const { toFieldErrors } = useApiError();

const token = computed(() => (typeof route.query.token === "string" ? route.query.token : ""));
const email = computed(() => (typeof route.query.email === "string" ? route.query.email : ""));
const password = ref("");
const confirm = ref("");
const error = ref<string | null>(null);
const linkInvalid = computed(() => token.value === "" || email.value === "");

const onSubmit = async () => {
  error.value = null;
  if (!password.value) return (error.value = t("validation.required"));
  if (password.value.length < 8) return (error.value = t("validation.minLength", { min: 8 }));
  if (password.value !== confirm.value) return (error.value = t("auth.reset.mismatch"));
  try {
    await auth.acceptInvite({ token: token.value, email: email.value, password: password.value });
    await navigateTo("/tenant");
  } catch (err) {
    const f = toFieldErrors(err);
    error.value = f ? Object.values(f)[0]! : t("auth.acceptInvite.invalid");
  }
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("auth.acceptInvite.title") }}</h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("auth.acceptInvite.subtitle") }}</p>
    </header>

    <div v-if="linkInvalid" class="rounded-lg border border-line-passive bg-surface-raised p-6 text-center">
      <p class="text-body text-ink">{{ t("auth.acceptInvite.invalid") }}</p>
      <NuxtLink to="/auth/forgot-password" class="mt-3 inline-block text-caption text-ink underline underline-offset-2">
        {{ t("auth.acceptInvite.forgotInstead") }}
      </NuxtLink>
    </div>

    <form v-else class="space-y-4" @submit.prevent="onSubmit">
      <Input :model-value="email" type="email" autocomplete="email" :label="t('auth.email')" size="lg" disabled />
      <Input v-model="password" type="password" autocomplete="new-password" :label="t('auth.reset.newPassword')" size="lg" />
      <Input v-model="confirm" type="password" autocomplete="new-password" :label="t('auth.reset.confirmPassword')" size="lg" />
      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
      <Button type="submit" variant="primary" size="lg" :loading="auth.loading" block>
        {{ t("auth.acceptInvite.submit") }}
      </Button>
      <p class="text-center text-caption text-ink-muted">
        <NuxtLink to="/auth/forgot-password" class="underline underline-offset-2 hover:text-ink">
          {{ t("auth.acceptInvite.forgotInstead") }}
        </NuxtLink>
      </p>
    </form>
  </div>
</template>
