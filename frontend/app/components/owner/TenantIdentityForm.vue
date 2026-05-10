<script setup lang="ts">
import { computed, ref } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { tenantIdentitySchema } from "~/schemas/tenant";
import type { Tenant } from "~/types/tenant";
import { useToast } from "~/composables/useToast";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{ tenant: Tenant }>();
const emit = defineEmits<{ saved: [tenant: Tenant] }>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);

const initialValues = {
  name: props.tenant.name,
  email: props.tenant.email,
  phone: props.tenant.phone,
  status: props.tenant.status,
};

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: toTypedSchema(tenantIdentitySchema),
  initialValues,
});

const [name] = defineField("name");
const [email] = defineField("email");
const [phone] = defineField("phone");
const [status] = defineField("status");

const statusOptions = computed(() => [
  { value: "invited", label: t("owner.tenants.status.invited") },
  { value: "active", label: t("owner.tenants.status.active") },
  { value: "notice_given", label: t("owner.tenants.status.notice_given") },
  { value: "moved_out", label: t("owner.tenants.status.moved_out") },
]);

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const updated = await useTenants().update(props.tenant.id, values);
    show(t("common.savedToast"), "success");
    emit("saved", updated);
  } finally {
    submitting.value = false;
  }
});
</script>

<template>
  <form class="space-y-4" @submit.prevent="onSubmit">
    <p class="text-caption text-ink-muted">
      {{ t("owner.tenants.detail.identityHelp") }}
    </p>

    <Input
      v-model="name"
      :label="t('owner.tenants.fields.name')"
      :error="errors.name"
    />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Input
        v-model="email"
        type="email"
        :label="t('owner.tenants.fields.email')"
        autocomplete="email"
        :error="errors.email"
      />
      <Input
        v-model="phone"
        type="tel"
        :label="t('owner.tenants.fields.phone')"
        autocomplete="tel"
        :error="errors.phone"
      />
    </div>

    <Select
      v-model="status"
      :options="statusOptions"
      :label="t('owner.tenants.fields.status')"
      :error="errors.status"
    />

    <div class="flex justify-end pt-2">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.tenants.detail.save") }}
      </Button>
    </div>
  </form>
</template>
