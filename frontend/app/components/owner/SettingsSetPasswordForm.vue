<script setup lang="ts">
import { ref } from "vue";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";
import { useToast } from "~/composables/useToast";

const { t } = useI18n();
const { show } = useToast();
const { toFieldErrors } = useApiError();
const auth = useAuthStore();
const password = ref("");
const confirm = ref("");
const error = ref<string | null>(null);
const submitting = ref(false);

const onSubmit = async () => {
  error.value = null;
  if (password.value.length < 8) return (error.value = t("owner.settings.profile.passwordTooShort"));
  if (password.value !== confirm.value) return (error.value = t("owner.settings.profile.passwordMismatch"));
  submitting.value = true;
  try {
    auth.setUser(await useOwnerSettings().setPassword(password.value));
    show(t("owner.settings.profile.passwordSetToast"), "success");
  } catch (err) {
    const f = toFieldErrors(err);
    error.value = f ? Object.values(f)[0]! : t("common.genericError");
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <form class="space-y-4" @submit.prevent="onSubmit">
    <Input
      v-model="password"
      type="password"
      autocomplete="new-password"
      :label="t('owner.settings.profile.newPassword')"
    />
    <Input
      v-model="confirm"
      type="password"
      autocomplete="new-password"
      :label="t('owner.settings.profile.confirmPassword')"
    />
    <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
    <div class="flex justify-end">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.settings.profile.setPassword") }}
      </Button>
    </div>
  </form>
</template>
