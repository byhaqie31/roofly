<script setup lang="ts">
import { ref, watch } from "vue";
import Modal from "~/components/ui/Modal.vue";
import Button from "~/components/ui/Button.vue";
import type { AdminOwner } from "~/types/admin";

const props = defineProps<{ open: boolean; owner: AdminOwner; mode: "suspend" | "unsuspend" }>();
const emit = defineEmits<{ "update:open": [v: boolean]; done: [owner: AdminOwner] }>();
const { t } = useI18n();
const { show } = useToast();

const reason = ref("");
const busy = ref(false);
const error = ref<string | null>(null);
watch(() => props.open, (o) => { if (o) { reason.value = ""; error.value = null; } });

const confirm = async () => {
  error.value = null;
  if (props.mode === "suspend" && reason.value.trim().length < 10) { error.value = t("admin.owners.detail.suspend.reasonMin"); return; }
  busy.value = true;
  try {
    const updated = props.mode === "suspend"
      ? await useAdminOwners().suspend(props.owner.id, reason.value.trim())
      : await useAdminOwners().unsuspend(props.owner.id);
    show(t(`admin.owners.detail.${props.mode}.doneToast`), "success");
    emit("done", updated);
    emit("update:open", false);
  } catch (e) {
    error.value = (e as { data?: { message?: string } })?.data?.message ?? (e as Error)?.message ?? t("common.genericError");
  } finally {
    busy.value = false;
  }
};
</script>

<template>
  <Modal :open="open" :title="t(`admin.owners.detail.${mode}.title`)" :description="t(`admin.owners.detail.${mode}.description`, { name: owner.name })" size="sm" @update:open="$emit('update:open', $event)">
    <div v-if="mode === 'suspend'">
      <label class="block">
        <span class="mb-1.5 block text-caption text-ink-strong">{{ t("admin.common.reason") }}</span>
        <textarea v-model="reason" rows="3" class="w-full rounded-sm border border-line-passive bg-surface-page p-3 text-body outline-none focus:border-line-interactive focus:shadow-focus" />
      </label>
      <p class="mt-1 text-micro text-ink-faint">{{ t("admin.owners.detail.suspend.help") }}</p>
    </div>
    <p v-else class="text-body text-ink-muted">{{ t("admin.owners.detail.unsuspend.help") }}</p>
    <p v-if="error" class="mt-3 text-caption text-accent" role="alert">{{ error }}</p>
    <template #footer>
      <Button variant="ghost" @click="$emit('update:open', false)">{{ t("common.cancel") }}</Button>
      <Button :variant="mode === 'suspend' ? 'accent' : 'primary'" :loading="busy" @click="confirm">{{ t(`admin.owners.detail.${mode}.cta`) }}</Button>
    </template>
  </Modal>
</template>
