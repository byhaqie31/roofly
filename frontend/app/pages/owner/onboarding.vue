<script setup lang="ts">
import { ref } from "vue";
import Button from "~/components/ui/Button.vue";
import OwnerPurposePicker from "~/components/owner/OwnerPurposePicker.vue";
import { useToast } from "~/composables/useToast";
import type { OwnerPurpose } from "~/types/auth";

definePageMeta({ layout: "onboarding" });

const { t } = useI18n();
useHead({ title: () => t("owner.onboarding.title") });
const { show } = useToast();
const auth = useAuthStore();

const purposes = ref<OwnerPurpose[]>([]);
const submitting = ref(false);

const finish = async (chosen: OwnerPurpose[]) => {
  submitting.value = true;
  try {
    const user = await useOwnerSettings().completeOnboarding({ purposes: chosen });
    auth.setUser(user);
    await navigateTo("/owner");
  } catch {
    show(t("common.genericError"), "danger");
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.onboarding.title") }}
      </h1>
      <p class="mt-2 text-body text-ink-muted">{{ t("owner.onboarding.subtitle") }}</p>
    </header>

    <OwnerPurposePicker v-model="purposes" />

    <div class="mt-6 space-y-3">
      <Button
        variant="primary"
        size="lg"
        block
        :disabled="purposes.length === 0"
        :loading="submitting"
        @click="finish(purposes)"
      >
        {{ t("owner.onboarding.continue") }}
      </Button>
      <button
        type="button"
        class="block w-full text-center text-caption text-ink-muted underline underline-offset-4 hover:text-ink"
        :disabled="submitting"
        @click="finish(['rental'])"
      >
        {{ t("owner.onboarding.skip") }}
      </button>
    </div>
  </div>
</template>
