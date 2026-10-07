<script setup lang="ts">
import { Mail, Bell, CheckCircle2, Loader2 } from "lucide-vue-next";

const { t } = useI18n();

const email = ref("");
// Honeypot (see WaitlistService): rendered off-screen, never filled by a person.
const website = ref("");
const submitted = ref(false);
const submitting = ref(false);
const error = ref<string | null>(null);

const isValidEmail = (value: string) =>
  /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());

const onSubmit = async () => {
  error.value = null;

  if (!isValidEmail(email.value)) {
    error.value = t("marketing.emailCapture.invalid");
    return;
  }

  submitting.value = true;
  try {
    // First-party store (POST /waitlist). The backend records the lead and,
    // when tracking is on, the waitlist_signup event — no client beacon here.
    await useWaitlist().join({ email: email.value.trim(), website: website.value });
    submitted.value = true;
  } catch {
    error.value = t("marketing.emailCapture.submitError");
  } finally {
    submitting.value = false;
  }
};
</script>

<template>
  <section
    id="email-capture"
    class="px-6 lg:px-12 py-20 lg:py-28 max-w-3xl mx-auto text-center"
  >
    <h2
      class="text-display-sub md:text-display-section font-semibold tracking-tight leading-[1.05]"
      style="color: #f7f4ed"
    >
      {{ t("marketing.emailCapture.title") }}
    </h2>
    <p
      class="mt-4 text-body-lg max-w-xl mx-auto"
      style="color: rgba(247, 244, 237, 0.78)"
    >
      {{ t("marketing.emailCapture.subtitle") }}
    </p>

    <Transition
      mode="out-in"
      enter-active-class="transition-all duration-500 ease-out"
      leave-active-class="transition-all duration-300 ease-in"
      enter-from-class="opacity-0 translate-y-2"
      leave-to-class="opacity-0"
    >
      <!-- Form view -->
      <form
        v-if="!submitted"
        :key="'form'"
        class="mt-10 flex flex-col items-center gap-3"
        @submit.prevent="onSubmit"
      >
        <!-- Honeypot: off-screen, out of the tab order and autofill; bots that fill every field trip it -->
        <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
          <label>
            Website
            <input v-model="website" type="text" name="website" tabindex="-1" autocomplete="off" />
          </label>
        </div>
        <div class="w-full max-w-md flex flex-col sm:flex-row items-stretch gap-3 sm:gap-0 sm:rounded-pill sm:overflow-hidden sm:bg-[rgba(247,244,237,0.06)] sm:shadow-[inset_0_0_0_1px_rgba(247,244,237,0.15)]">
          <label
            class="flex flex-1 min-w-0 items-stretch rounded-pill sm:rounded-none overflow-hidden bg-[rgba(247,244,237,0.06)] shadow-[inset_0_0_0_1px_rgba(247,244,237,0.15)] sm:bg-transparent sm:shadow-none"
          >
            <span class="inline-flex items-center pl-5 shrink-0" style="color: rgba(247, 244, 237, 0.5)">
              <Mail :size="18" :stroke-width="1.75" />
            </span>
            <input
              v-model="email"
              type="email"
              autocomplete="email"
              inputmode="email"
              :placeholder="t('marketing.emailCapture.placeholder')"
              :disabled="submitting"
              class="flex-1 min-w-0 bg-transparent px-3 py-3 text-body outline-none placeholder:text-[rgba(247,244,237,0.4)] disabled:opacity-60"
              style="color: #f7f4ed"
              :aria-invalid="error !== null"
            />
          </label>
          <button
            type="submit"
            :disabled="submitting"
            class="inline-flex items-center justify-center gap-2 px-5 py-3 sm:m-1 rounded-pill text-body sm:text-caption font-semibold transition-all hover:scale-[1.02] active:scale-100 disabled:opacity-70 disabled:cursor-wait disabled:hover:scale-100 shrink-0"
            style="background-color: #e76a3f; color: #1c1a17"
          >
            <template v-if="submitting">
              <Loader2 :size="16" :stroke-width="2" class="animate-spin" />
              {{ t("marketing.emailCapture.submitting") }}
            </template>
            <template v-else>
              <Bell :size="16" :stroke-width="2" />
              {{ t("marketing.emailCapture.submit") }}
            </template>
          </button>
        </div>
        <p
          v-if="error"
          role="alert"
          class="text-caption"
          style="color: #f87171"
        >
          {{ error }}
        </p>
      </form>

      <!-- Success view -->
      <div
        v-else
        :key="'success'"
        class="mt-10 inline-flex items-center gap-3 rounded-pill px-6 py-4"
        style="
          background: rgba(231, 106, 63, 0.12);
          box-shadow: inset 0 0 0 1px rgba(231, 106, 63, 0.3);
        "
      >
        <CheckCircle2 :size="20" :stroke-width="1.75" style="color: #e76a3f" />
        <p class="text-body font-medium" style="color: #f7f4ed">
          {{ t("marketing.emailCapture.success") }}
        </p>
      </div>
    </Transition>
  </section>
</template>
