<script setup lang="ts">
import { ref } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { tenantEmergencyContactSchema } from "~/schemas/tenant";
import type { Tenant } from "~/types/tenant";
import { useToast } from "~/composables/useToast";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{ tenant: Tenant }>();
const emit = defineEmits<{ saved: [tenant: Tenant] }>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);

const e = props.tenant.emergencyContact ?? {};
const initialValues = {
  name: e.name ?? "",
  phone: e.phone ?? "",
  relationship: e.relationship ?? "",
};

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: toTypedSchema(tenantEmergencyContactSchema),
  initialValues,
});

const [name] = defineField("name");
const [phone] = defineField("phone");
const [relationship] = defineField("relationship");

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const updated = await useTenants().update(props.tenant.id, {
      emergencyContact: {
        name: values.name || undefined,
        phone: values.phone || undefined,
        relationship: values.relationship || undefined,
      },
    });
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
      {{ t("owner.tenants.detail.emergencyHelp") }}
    </p>

    <Input
      v-model="name"
      :label="t('owner.tenants.fields.emergencyName')"
      :placeholder="t('owner.tenants.placeholders.emergencyName')"
      :error="errors.name"
    />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Input
        v-model="phone"
        type="tel"
        :label="t('owner.tenants.fields.emergencyPhone')"
        :placeholder="t('owner.tenants.placeholders.phone')"
        :error="errors.phone"
      />
      <Input
        v-model="relationship"
        :label="t('owner.tenants.fields.relationship')"
        :placeholder="t('owner.tenants.placeholders.relationship')"
        :error="errors.relationship"
      />
    </div>

    <div class="flex justify-end pt-2">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.tenants.detail.save") }}
      </Button>
    </div>
  </form>
</template>
