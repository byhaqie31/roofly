<script setup lang="ts">
import { computed, ref, watch } from "vue";
import Modal from "~/components/ui/Modal.vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import { warningText } from "~/utils/warningText";
import type { AdminOwner, WarnTemplate } from "~/types/admin";

const props = defineProps<{ open: boolean; owner: AdminOwner }>();
const emit = defineEmits<{ "update:open": [v: boolean]; sent: [] }>();
const { t } = useI18n();
const { show } = useToast();

const plus7 = () => new Date(Date.now() + 7 * 86_400_000).toISOString().slice(0, 10);
const template = ref<WarnTemplate>("payment_overdue");
const suspendOn = ref(plus7());
const extraLine = ref("");
const sending = ref(false);
const error = ref<string | null>(null);

watch(() => props.open, (o) => { if (o) { template.value = "payment_overdue"; suspendOn.value = plus7(); extraLine.value = ""; error.value = null; } });

const templateOptions = computed(() => [{ value: "payment_overdue", label: t("admin.owners.detail.warn.templates.payment_overdue") }]);
// Preview stays English regardless of admin UI locale — the SP1 email (OwnerWarning::toMail) is English-only.
const preview = computed(() => warningText(template.value, suspendOn.value, extraLine.value || null));

const send = async () => {
  error.value = null;
  if (!suspendOn.value || suspendOn.value <= new Date().toISOString().slice(0, 10)) { error.value = t("admin.owners.detail.warn.dateFuture"); return; }
  sending.value = true;
  try {
    await useAdminOwners().warn(props.owner.id, { template: template.value, suspendOn: suspendOn.value, extraLine: extraLine.value || undefined });
    show(t("admin.owners.detail.warn.sentToast"), "success");
    emit("sent");
    emit("update:open", false);
  } catch (e) {
    error.value = (e as { data?: { message?: string } })?.data?.message ?? (e as Error)?.message ?? t("common.genericError");
  } finally {
    sending.value = false;
  }
};
</script>

<template>
  <Modal :open="open" :title="t('admin.owners.detail.warn.title')" :description="t('admin.owners.detail.warn.description', { name: owner.name })" @update:open="$emit('update:open', $event)">
    <div class="space-y-4">
      <Select v-model="template" :options="templateOptions" :label="t('admin.owners.detail.warn.template')" />
      <Input v-model="suspendOn" type="date" :label="t('admin.owners.detail.warn.suspendOn')" />
      <Input v-model="extraLine" :label="t('admin.owners.detail.warn.extraLine')" :placeholder="t('admin.owners.detail.warn.extraLinePlaceholder')" />
      <div>
        <p class="mb-1.5 text-caption text-ink-strong">{{ t("admin.owners.detail.warn.preview") }}</p>
        <pre class="whitespace-pre-wrap rounded-sm border border-line-passive bg-surface-page p-3 text-caption text-ink font-sans">{{ preview }}</pre>
        <p class="mt-1 text-micro text-ink-faint">{{ t("admin.owners.detail.warn.channels") }}</p>
      </div>
      <p v-if="error" class="text-caption text-accent" role="alert">{{ error }}</p>
    </div>
    <template #footer>
      <Button variant="ghost" @click="$emit('update:open', false)">{{ t("common.cancel") }}</Button>
      <Button variant="primary" :loading="sending" @click="send">{{ t("admin.owners.detail.warn.send") }}</Button>
    </template>
  </Modal>
</template>
