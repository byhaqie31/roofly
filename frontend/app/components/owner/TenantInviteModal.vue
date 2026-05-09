<script setup lang="ts">
import { ref, watch } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { tenantInputSchema } from "~/schemas/tenant";
import type { Tenant, TenantInput } from "~/types/tenant";
import { useToast } from "~/composables/useToast";
import Modal from "~/components/ui/Modal.vue";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{
  "update:open": [value: boolean];
  invited: [tenant: Tenant];
}>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);

const initialValues: TenantInput = { name: "", email: "", phone: "" };

const { defineField, handleSubmit, errors, resetForm } = useForm<TenantInput>({
  validationSchema: toTypedSchema(tenantInputSchema),
  initialValues,
});

const [name] = defineField("name");
const [email] = defineField("email");
const [phone] = defineField("phone");

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const created = await useTenants().invite(values);
    show(t("owner.tenants.invitedToast"), "success");
    emit("invited", created);
    emit("update:open", false);
  } finally {
    submitting.value = false;
  }
});

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) resetForm({ values: initialValues });
  },
);
</script>

<template>
  <Modal
    :open="open"
    :title="t('owner.tenants.inviteTitle')"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <form
      id="tenant-invite-form"
      class="space-y-4"
      @submit.prevent="onSubmit"
    >
      <p class="text-caption text-ink-muted">
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
    </form>

    <template #footer>
      <Button
        variant="ghost"
        :disabled="submitting"
        @click="emit('update:open', false)"
      >
        {{ t("common.cancel") }}
      </Button>
      <Button
        type="submit"
        form="tenant-invite-form"
        variant="primary"
        :loading="submitting"
      >
        {{ t("owner.tenants.inviteCta") }}
      </Button>
    </template>
  </Modal>
</template>
