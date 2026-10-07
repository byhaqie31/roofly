<script setup lang="ts">
import { computed, ref } from "vue";
import { Bug, CircleCheck, Headset, Lightbulb, MessageCircleQuestion } from "lucide-vue-next";
import Modal from "~/components/ui/Modal.vue";
import Button from "~/components/ui/Button.vue";
import { ENQUIRY_TYPES, type EnquiryType } from "~/types/support";
import { pageLabelFor } from "~/utils/pageLabel";

// Floating help button for the owner + tenant shells. Sends an issue / feedback /
// question to admin → Enquiries → Messages (super admins get an email). Name and
// email come from the session; the current page is attached so we know where the
// problem happened. Gated by useEnv().showSupportWidget at the mount site.
const { t } = useI18n();
const auth = useAuthStore();
const route = useRoute();

const open = ref(false);
const type = ref<EnquiryType>("issue");
const message = ref("");
const pageUrl = ref("");
const sending = ref(false);
const sent = ref(false);
const error = ref<string | null>(null);
const submitted = ref(false);

const icons = { issue: Bug, feedback: Lightbulb, question: MessageCircleQuestion } as const;

const tooShort = computed(() => message.value.trim().length < 5);
const messageError = computed(() => (submitted.value && tooShort.value ? t("support.tooShort") : undefined));

const openForm = () => {
  type.value = "issue";
  message.value = "";
  pageUrl.value = route.fullPath; // captured now, before the modal steals focus
  sent.value = false;
  submitted.value = false;
  error.value = null;
  open.value = true;
};

const send = async () => {
  submitted.value = true;
  error.value = null;
  if (tooShort.value) return;
  sending.value = true;
  try {
    await useSupport().send({ type: type.value, message: message.value, pageUrl: pageUrl.value, pageLabel: pageLabelFor(pageUrl.value) });
    sent.value = true;
  } catch (e) {
    error.value = (e as { data?: { message?: string } })?.data?.message ?? t("common.genericError");
  } finally {
    sending.value = false;
  }
};
</script>

<template>
  <button
    type="button"
    class="group fixed bottom-6 right-6 z-40 inline-flex h-12 items-center gap-2 rounded-pill bg-ink pl-3.5 pr-3.5 text-caption font-medium text-surface-page shadow-lg outline-none transition-all hover:bg-ink-strong hover:pr-5 focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2"
    :aria-label="t('support.button')"
    @click="openForm"
  >
    <Headset :size="20" :stroke-width="1.75" />
    <span class="hidden max-w-0 overflow-hidden whitespace-nowrap transition-all duration-200 group-hover:max-w-[12rem] group-focus-visible:max-w-[12rem] md:inline-block">
      {{ t("support.button") }}
    </span>
  </button>

  <Modal
    :open="open"
    :title="sent ? t('support.sentTitle') : t('support.title')"
    :description="sent ? undefined : t('support.description', { email: auth.user?.email ?? '' })"
    @update:open="open = $event"
  >
    <div v-if="sent" class="flex flex-col items-center gap-3 py-4 text-center">
      <span class="inline-flex h-12 w-12 items-center justify-center rounded-pill bg-surface-hover text-accent">
        <CircleCheck :size="24" :stroke-width="1.75" />
      </span>
      <p class="text-body text-ink-muted">{{ t("support.sentBody", { email: auth.user?.email ?? "" }) }}</p>
    </div>

    <form v-else class="space-y-4" @submit.prevent="send">
      <fieldset>
        <legend class="mb-1.5 block text-caption font-normal text-ink-strong">{{ t("support.typeLabel") }}</legend>
        <div class="grid grid-cols-3 gap-2" role="radiogroup">
          <button
            v-for="k in ENQUIRY_TYPES"
            :key="k"
            type="button"
            role="radio"
            :aria-checked="type === k"
            class="flex flex-col items-center gap-1.5 rounded-sm border px-2 py-3 text-caption outline-none transition focus-visible:shadow-focus"
            :class="type === k ? 'border-line-interactive bg-surface-hover text-ink font-medium' : 'border-line-passive text-ink-muted hover:bg-surface-hover'"
            @click="type = k"
          >
            <component :is="icons[k]" :size="18" :stroke-width="1.75" />
            {{ t(`support.types.${k}`) }}
          </button>
        </div>
      </fieldset>

      <div>
        <label for="support-message" class="mb-1.5 block text-caption font-normal text-ink-strong">{{ t("support.messageLabel") }}</label>
        <textarea
          id="support-message"
          v-model="message"
          rows="5"
          maxlength="5000"
          :placeholder="t(`support.placeholders.${type}`)"
          class="w-full rounded-sm border border-line-passive bg-surface-page px-3 py-2 text-body text-ink outline-none transition focus:border-line-interactive focus:shadow-focus"
        />
        <span v-if="messageError" class="mt-1.5 block text-caption text-accent" role="alert">{{ messageError }}</span>
        <p class="mt-1.5 text-micro text-ink-faint">{{ t("support.pageNote") }}</p>
      </div>

      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
    </form>

    <template #footer>
      <template v-if="sent">
        <Button variant="primary" @click="open = false">{{ t("common.close") }}</Button>
      </template>
      <template v-else>
        <Button variant="ghost" :disabled="sending" @click="open = false">{{ t("common.cancel") }}</Button>
        <Button variant="primary" :loading="sending" @click="send">{{ t("support.send") }}</Button>
      </template>
    </template>
  </Modal>
</template>
