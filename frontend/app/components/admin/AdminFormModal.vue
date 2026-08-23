<script setup lang="ts">
import { computed, ref, watch } from "vue";
import Modal from "~/components/ui/Modal.vue";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";
import type { AdminPermission, AdminUser, PermissionCatalogue } from "~/types/admin";

const props = defineProps<{ open: boolean; catalogue: PermissionCatalogue; editing: AdminUser | null }>();
const emit = defineEmits<{ "update:open": [v: boolean]; saved: [admin: AdminUser] }>();
const { t } = useI18n();
const { show } = useToast();
const { isSuperAdmin } = useAdminPermissions();
const { toFieldErrors } = useApiError();

const name = ref("");
const email = ref("");
const superAdmin = ref(false);
const selected = ref<Set<AdminPermission>>(new Set());
const busy = ref(false);
const errors = ref<Record<string, string>>({});

watch(() => props.open, (o) => {
  if (!o) return;
  errors.value = {};
  name.value = props.editing?.name ?? "";
  email.value = props.editing?.email ?? "";
  superAdmin.value = props.editing?.isSuperAdmin ?? false;
  selected.value = new Set(props.editing ? props.editing.permissions : props.catalogue.preset);
});

const toggle = (key: AdminPermission) => { const s = new Set(selected.value); s.has(key) ? s.delete(key) : s.add(key); selected.value = s; };
const applyPreset = () => { selected.value = new Set(props.catalogue.preset); };
const isEdit = computed(() => props.editing !== null);

const save = async () => {
  errors.value = {};
  if (!isEdit.value && (!name.value.trim() || !email.value.trim())) { errors.value = { name: t("validation.required") }; return; }
  busy.value = true;
  try {
    const permissions = [...selected.value];
    const saved = isEdit.value
      ? await useAdminAdmins().update(props.editing!.id, { permissions, ...(isSuperAdmin.value ? { isSuperAdmin: superAdmin.value } : {}) })
      : await useAdminAdmins().create({ name: name.value.trim(), email: email.value.trim(), permissions, ...(isSuperAdmin.value && superAdmin.value ? { isSuperAdmin: true } : {}) });
    show(t(isEdit.value ? "admin.settings.admins.updatedToast" : "admin.settings.admins.invitedToast"), "success");
    emit("saved", saved);
    emit("update:open", false);
  } catch (e) {
    errors.value = toFieldErrors(e) ?? { form: (e as Error)?.message ?? t("common.genericError") };
  } finally {
    busy.value = false;
  }
};
</script>

<template>
  <Modal :open="open" :title="t(isEdit ? 'admin.settings.admins.editTitle' : 'admin.settings.admins.createTitle')" size="lg" @update:open="$emit('update:open', $event)">
    <div class="space-y-4">
      <template v-if="!isEdit">
        <Input v-model="name" :label="t('auth.fullName')" :error="errors.name" />
        <Input v-model="email" type="email" :label="t('auth.email')" :error="errors.email" />
      </template>
      <div>
        <div class="mb-2 flex items-center justify-between">
          <span class="text-caption text-ink-strong">{{ t("admin.settings.admins.permissions") }}</span>
          <Button variant="ghost" size="sm" @click="applyPreset">{{ t("admin.settings.admins.applyPreset") }}</Button>
        </div>
        <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2">
          <li v-for="p in catalogue.permissions" :key="p.key">
            <label class="flex items-start gap-2 rounded-sm border border-line-passive p-2 text-caption">
              <input type="checkbox" class="mt-0.5 accent-admin" :checked="selected.has(p.key)" :disabled="superAdmin" @change="toggle(p.key)" />
              <span><span class="text-ink">{{ t(`admin.settings.admins.keys.${p.key}`) }}</span><span class="block text-micro text-ink-faint">{{ p.key }}</span></span>
            </label>
          </li>
        </ul>
        <p v-if="errors.permissions" class="mt-1 text-caption text-accent">{{ errors.permissions }}</p>
      </div>
      <label v-if="isSuperAdmin" class="flex items-center gap-2 text-caption text-ink-strong">
        <input v-model="superAdmin" type="checkbox" class="accent-admin" />{{ t("admin.settings.admins.superAdmin") }}
        <span class="text-micro text-ink-faint">— {{ t("admin.settings.admins.superAdminHelp") }}</span>
      </label>
      <p v-if="errors.form || errors.isSuperAdmin || errors.disabled" class="text-caption text-accent" role="alert">{{ errors.form ?? errors.isSuperAdmin ?? errors.disabled }}</p>
    </div>
    <template #footer>
      <Button variant="ghost" @click="$emit('update:open', false)">{{ t("common.cancel") }}</Button>
      <Button variant="primary" :loading="busy" @click="save">{{ t(isEdit ? "common.save" : "admin.settings.admins.sendInvite") }}</Button>
    </template>
  </Modal>
</template>
