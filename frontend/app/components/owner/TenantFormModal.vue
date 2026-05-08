<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import {
  tenantInputSchema,
  tenantUpdateSchema,
} from "~/schemas/tenant";
import type { Tenant, TenantUpdate } from "~/types/tenant";
import { useToast } from "~/composables/useToast";
import Modal from "~/components/ui/Modal.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";

const props = withDefaults(
  defineProps<{
    open: boolean;
    tenant?: Tenant | null;
  }>(),
  { tenant: null },
);

const emit = defineEmits<{
  "update:open": [value: boolean];
  saved: [tenant: Tenant];
  deleted: [tenantId: string];
}>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);
const deleting = ref(false);
const confirmingDelete = ref(false);

const isEditMode = computed(() => !!props.tenant);

interface FormValues {
  name: string;
  email: string;
  phone: string;
  status?: Tenant["status"];
}

const buildInitialValues = (): FormValues => ({
  name: props.tenant?.name ?? "",
  email: props.tenant?.email ?? "",
  phone: props.tenant?.phone ?? "",
  status: props.tenant?.status,
});

const validationSchema = computed(() =>
  isEditMode.value
    ? toTypedSchema(tenantUpdateSchema)
    : toTypedSchema(tenantInputSchema),
);

const { defineField, handleSubmit, errors, resetForm } = useForm<FormValues>({
  validationSchema,
  initialValues: buildInitialValues(),
});

const [name] = defineField("name");
const [email] = defineField("email");
const [phone] = defineField("phone");
const [status] = defineField("status");

const statusOptions = computed(() => [
  { value: "invited", label: t("owner.tenants.status.invited") },
  { value: "active", label: t("owner.tenants.status.active") },
  { value: "moved_out", label: t("owner.tenants.status.moved_out") },
]);

const modalTitle = computed(() => {
  if (confirmingDelete.value) return t("owner.tenants.deleteConfirm.title");
  return isEditMode.value
    ? t("owner.tenants.editTitle")
    : t("owner.tenants.inviteTitle");
});

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    if (isEditMode.value && props.tenant) {
      const patch: TenantUpdate = {
        name: values.name,
        email: values.email,
        phone: values.phone,
        status: values.status,
      };
      const updated = await useTenants().update(props.tenant.id, patch);
      show(t("common.savedToast"), "success");
      emit("saved", updated);
    } else {
      const created = await useTenants().invite({
        name: values.name,
        email: values.email,
        phone: values.phone,
      });
      show(t("owner.tenants.invitedToast"), "success");
      emit("saved", created);
    }
    emit("update:open", false);
  } finally {
    submitting.value = false;
  }
});

const onConfirmDelete = async () => {
  if (!props.tenant) return;
  deleting.value = true;
  try {
    await useTenants().remove(props.tenant.id);
    show(t("owner.tenants.deletedToast"), "success");
    emit("deleted", props.tenant.id);
    emit("update:open", false);
  } finally {
    deleting.value = false;
  }
};

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      resetForm({ values: buildInitialValues() });
      confirmingDelete.value = false;
    }
  },
);
</script>

<template>
  <Modal
    :open="open"
    :title="modalTitle"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <div v-if="confirmingDelete">
      <p class="text-body text-ink">
        {{
          t("owner.tenants.deleteConfirm.body", {
            name: props.tenant?.name ?? "",
          })
        }}
      </p>
    </div>

    <form
      v-else
      id="tenant-form"
      class="space-y-4"
      @submit.prevent="onSubmit"
    >
      <p v-if="!isEditMode" class="text-caption text-ink-muted">
        {{ t("owner.tenants.inviteHelp") }}
      </p>

      <Input
        v-model="name"
        :label="t('owner.tenants.fields.name')"
        :placeholder="t('owner.tenants.placeholders.name')"
        :error="errors.name"
      />
      <Input
        v-model="email"
        type="email"
        :label="t('owner.tenants.fields.email')"
        :placeholder="t('owner.tenants.placeholders.email')"
        autocomplete="email"
        :error="errors.email"
      />
      <Input
        v-model="phone"
        type="tel"
        :label="t('owner.tenants.fields.phone')"
        :placeholder="t('owner.tenants.placeholders.phone')"
        autocomplete="tel"
        :error="errors.phone"
      />
      <Select
        v-if="isEditMode"
        v-model="status"
        :options="statusOptions"
        :label="t('owner.tenants.fields.status')"
        :error="errors.status"
      />
    </form>

    <template #footer>
      <template v-if="confirmingDelete">
        <Button
          variant="ghost"
          :disabled="deleting"
          @click="confirmingDelete = false"
        >
          {{ t("common.cancel") }}
        </Button>
        <Button
          variant="accent"
          :loading="deleting"
          @click="onConfirmDelete"
        >
          {{ t("owner.tenants.deleteConfirm.confirm") }}
        </Button>
      </template>
      <template v-else>
        <Button
          v-if="isEditMode"
          variant="ghost"
          :disabled="submitting"
          @click="confirmingDelete = true"
        >
          {{ t("owner.tenants.delete") }}
        </Button>
        <div v-if="isEditMode" class="flex-1" />
        <Button
          variant="ghost"
          :disabled="submitting"
          @click="emit('update:open', false)"
        >
          {{ t("common.cancel") }}
        </Button>
        <Button
          type="submit"
          form="tenant-form"
          variant="primary"
          :loading="submitting"
        >
          {{
            isEditMode
              ? t("owner.tenants.save")
              : t("owner.tenants.inviteCta")
          }}
        </Button>
      </template>
    </template>
  </Modal>
</template>
