<script setup lang="ts">
import { computed } from "vue";
import { ChevronRight, Mail, MessageCircle, Phone } from "lucide-vue-next";
import Modal from "~/components/ui/Modal.vue";
import { LEGAL } from "~/config/legal";
import { contactOptions } from "~/utils/legal";

// "Contact us" pop-up behind the slim footer's link (auth, onboarding,
// suspended): email, call and WhatsApp from config/legal.ts.
defineProps<{ open: boolean }>();
defineEmits<{ "update:open": [value: boolean] }>();

const { t } = useI18n();
const options = computed(() => contactOptions(LEGAL, { whatsappText: t("legal.contactUs.whatsappText") }));
const icons = { email: Mail, call: Phone, whatsapp: MessageCircle } as const;
</script>

<template>
  <Modal
    :open="open"
    size="sm"
    :title="t('legal.contactUs.title')"
    :description="t('legal.contactUs.description')"
    @update:open="$emit('update:open', $event)"
  >
    <ul class="space-y-2">
      <li v-for="o in options" :key="o.key">
        <a
          :href="o.href"
          :target="o.external ? '_blank' : undefined"
          :rel="o.external ? 'noopener noreferrer' : undefined"
          class="flex items-center gap-3 rounded-md border border-line-passive px-4 py-3 text-ink hover:bg-surface-hover focus-visible:shadow-focus transition-colors"
        >
          <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-pill bg-surface-hover text-ink-body">
            <component :is="icons[o.key]" :size="18" :stroke-width="1.75" aria-hidden="true" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="block text-body">{{ t(`legal.contactUs.${o.key}`) }}</span>
            <span class="block truncate text-caption text-ink-muted tabular-nums">{{ o.value }}</span>
          </span>
          <ChevronRight :size="16" :stroke-width="1.5" class="shrink-0 text-ink-faint" aria-hidden="true" />
        </a>
      </li>
    </ul>
  </Modal>
</template>
