<script setup lang="ts">
import { ref, watch } from "vue";
import Modal from "~/components/ui/Modal.vue";
import Button from "~/components/ui/Button.vue";
import { formatAdminDate } from "~/utils/adminDate";
import type { AdminLead } from "~/types/analytics";

// Confirm step before Enquiries emails a waitlist lead the sign-up invitation
// (WaitlistInvitation on the backend). It reaches a real inbox, so never one-click.
const props = defineProps<{ open: boolean; lead: AdminLead | null }>();
const emit = defineEmits<{ "update:open": [v: boolean]; sent: [lead: AdminLead] }>();
const { t } = useI18n();
const { show } = useToast();

const sending = ref(false);
const error = ref<string | null>(null);

watch(() => props.open, (o) => { if (o) error.value = null; });

const send = async () => {
  if (!props.lead) return;
  error.value = null;
  sending.value = true;
  try {
    const updated = await useAdminAnalytics().invite(props.lead.id);
    show(t("admin.enquiries.invite.sentToast", { email: updated.email }), "success");
    emit("sent", updated);
    emit("update:open", false);
  } catch (e) {
    error.value = (e as { data?: { message?: string } })?.data?.message ?? (e as Error)?.message ?? t("common.genericError");
  } finally {
    sending.value = false;
  }
};
</script>

<template>
  <Modal
    :open="open"
    :title="lead?.invitedAt ? t('admin.enquiries.invite.resendTitle') : t('admin.enquiries.invite.title')"
    :description="t('admin.enquiries.invite.description', { email: lead?.email ?? '' })"
    @update:open="$emit('update:open', $event)"
  >
    <div class="space-y-3">
      <p v-if="lead?.invitedAt" class="text-caption text-ink-muted">
        {{ t("admin.enquiries.invite.lastSent", { date: formatAdminDate(lead.invitedAt) }) }}
      </p>
      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
    </div>
    <template #footer>
      <Button variant="ghost" @click="$emit('update:open', false)">{{ t("common.cancel") }}</Button>
      <Button variant="primary" :loading="sending" @click="send">{{ t("admin.enquiries.invite.confirm") }}</Button>
    </template>
  </Modal>
</template>
