<script setup lang="ts">
import { computed, ref } from "vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";

definePageMeta({ layout: "auth-admin" });

const { t } = useI18n();
useHead({ title: () => t("auth.admin.acceptTitle") });

const route = useRoute();
const auth = useAuthStore();
const token = computed(() => String(route.query.token ?? ""));
const password = ref("");
const confirm = ref("");
const error = ref<string | null>(null);

const onSubmit = async () => {
  error.value = null;
  if (password.value.length < 8) {
    error.value = t("auth.admin.passwordTooShort");
    return;
  }
  if (password.value !== confirm.value) {
    error.value = t("auth.admin.passwordMismatch");
    return;
  }
  try {
    await auth.acceptAdminInvite(token.value, password.value);
    await navigateTo("/admin");
  } catch {
    error.value = t("auth.admin.inviteInvalid");
  }
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("auth.admin.acceptTitle") }}</h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("auth.admin.acceptSubtitle") }}</p>
    </header>

    <p v-if="!token" class="text-caption text-accent" role="alert">{{ t("auth.admin.inviteInvalid") }}</p>

    <form v-else class="space-y-4" @submit.prevent="onSubmit">
      <Input v-model="password" type="password" autocomplete="new-password" :label="t('auth.admin.newPassword')" size="lg" />
      <Input v-model="confirm" type="password" autocomplete="new-password" :label="t('auth.admin.confirmPassword')" size="lg" />
      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
      <Button type="submit" variant="primary" size="lg" :loading="auth.loading" block>
        {{ t("auth.admin.accept") }}
      </Button>
    </form>
  </div>
</template>
