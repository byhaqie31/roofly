<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Mail } from "lucide-vue-next";
import Modal from "~/components/ui/Modal.vue";
import Button from "~/components/ui/Button.vue";
import Select from "~/components/ui/Select.vue";
import Pill from "~/components/ui/Pill.vue";
import { formatAdminDateTime } from "~/utils/adminDate";
import { ENQUIRY_STATUSES, type AdminEnquiry, type EnquiryStatus } from "~/types/support";

// One message from the help button: full text, where it was sent from, and the
// track-only controls (status + internal note). "Reply by email" opens the admin's
// own mail app — replies never go through Roofly.
const props = defineProps<{ open: boolean; enquiry: AdminEnquiry | null }>();
const emit = defineEmits<{ "update:open": [v: boolean]; saved: [e: AdminEnquiry] }>();
const { t } = useI18n();
const { show } = useToast();

const status = ref<EnquiryStatus>("new");
const note = ref("");
const saving = ref(false);
const error = ref<string | null>(null);

watch(() => [props.open, props.enquiry] as const, ([o, e]) => {
  if (o && e) { status.value = e.status; note.value = e.adminNote ?? ""; error.value = null; }
});

const statusOptions = computed(() => ENQUIRY_STATUSES.map((s) => ({ value: s, label: t(`admin.enquiries.messages.status.${s}`) })));
const dirty = computed(() => !!props.enquiry && (status.value !== props.enquiry.status || note.value !== (props.enquiry.adminNote ?? "")));

const mailto = computed(() => {
  const e = props.enquiry;
  if (!e) return "#";
  const subject = `Re: your Roofly ${t(`admin.enquiries.messages.types.${e.type}`).toLowerCase()}`;
  const quoted = e.message.split("\n").map((l) => `> ${l}`).join("\n");
  return `mailto:${encodeURIComponent(e.email)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(`Hi ${e.name.split(" ")[0]},\n\n\n\n${quoted}`)}`;
});

const save = async () => {
  if (!props.enquiry) return;
  saving.value = true;
  error.value = null;
  try {
    const updated = await useAdminEnquiries().update(props.enquiry.id, { status: status.value, adminNote: note.value.trim() || null });
    show(t("admin.enquiries.messages.detail.savedToast"), "success");
    emit("saved", updated);
    emit("update:open", false);
  } catch (e) {
    error.value = (e as { data?: { message?: string } })?.data?.message ?? t("common.genericError");
  } finally {
    saving.value = false;
  }
};
</script>

<template>
  <Modal :open="open" size="lg" :title="enquiry ? enquiry.name : ''" @update:open="$emit('update:open', $event)">
    <div v-if="enquiry" class="space-y-5">
      <div class="flex flex-wrap items-center gap-2 text-caption text-ink-muted">
        <Pill :tone="enquiry.type === 'issue' ? 'high' : enquiry.type === 'question' ? 'medium' : 'low'">
          {{ t(`admin.enquiries.messages.types.${enquiry.type}`) }}
        </Pill>
        <span>{{ enquiry.email }}</span>
        <span v-if="enquiry.role">· {{ t(`admin.enquiries.messages.roles.${enquiry.role}`) }}</span>
        <span>· {{ formatAdminDateTime(enquiry.createdAt) }}</span>
      </div>

      <p class="whitespace-pre-wrap rounded-sm border border-line-passive bg-surface-page p-4 text-body text-ink">{{ enquiry.message }}</p>

      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-caption">
        <template v-if="enquiry.pageUrl || enquiry.pageLabel">
          <dt class="text-ink-muted">{{ t("admin.enquiries.messages.detail.page") }}</dt>
          <dd class="text-ink">
            <span class="font-medium">{{ enquiry.pageLabel ?? enquiry.pageUrl }}</span>
            <span v-if="enquiry.pageLabel && enquiry.pageUrl" class="ml-1.5 break-all font-mono text-micro text-ink-muted">{{ enquiry.pageUrl }}</span>
          </dd>
        </template>
        <template v-if="enquiry.handledByName">
          <dt class="text-ink-muted">{{ t("admin.enquiries.messages.detail.handledBy") }}</dt>
          <dd class="text-ink">
            {{ enquiry.handledByName }}<template v-if="enquiry.statusChangedAt"> · {{ formatAdminDateTime(enquiry.statusChangedAt) }}</template>
          </dd>
        </template>
      </dl>

      <div class="grid gap-4 sm:grid-cols-[12rem_1fr]">
        <Select v-model="status" :options="statusOptions" :label="t('admin.enquiries.messages.detail.status')" />
        <div>
          <label for="enquiry-note" class="mb-1.5 block text-caption font-normal text-ink-strong">{{ t("admin.enquiries.messages.detail.note") }}</label>
          <textarea
            id="enquiry-note"
            v-model="note"
            rows="3"
            maxlength="5000"
            :placeholder="t('admin.enquiries.messages.detail.notePlaceholder')"
            class="w-full rounded-sm border border-line-passive bg-surface-page px-3 py-2 text-body text-ink outline-none transition focus:border-line-interactive focus:shadow-focus"
          />
        </div>
      </div>

      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
    </div>

    <template #footer>
      <a
        :href="mailto"
        class="mr-auto inline-flex items-center gap-2 text-caption text-ink underline underline-offset-2"
      >
        <Mail :size="16" :stroke-width="1.75" />
        {{ t("admin.enquiries.messages.detail.reply") }}
      </a>
      <Button variant="ghost" @click="$emit('update:open', false)">{{ t("common.cancel") }}</Button>
      <Button variant="primary" :loading="saving" :disabled="!dirty" @click="save">{{ t("common.save") }}</Button>
    </template>
  </Modal>
</template>
