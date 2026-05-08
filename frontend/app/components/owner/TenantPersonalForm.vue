<script setup lang="ts">
import { ref } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { tenantPersonalSchema } from "~/schemas/tenant";
import type { Tenant } from "~/types/tenant";
import { useToast } from "~/composables/useToast";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";

const props = defineProps<{ tenant: Tenant }>();
const emit = defineEmits<{ saved: [tenant: Tenant] }>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);

const senToRinggit = (sen?: number) =>
  sen == null ? undefined : sen / 100;
const ringgitToSen = (rm?: number) =>
  rm == null ? undefined : Math.round(rm * 100);

const p = props.tenant.personal ?? {};
const initialValues = {
  icNumber: p.icNumber ?? "",
  dateOfBirth: p.dateOfBirth ?? "",
  occupation: p.occupation ?? "",
  employer: p.employer ?? "",
  monthlyIncome: senToRinggit(p.monthlyIncome),
  nationality: p.nationality ?? "",
};

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: toTypedSchema(tenantPersonalSchema),
  initialValues,
});

const [icNumber] = defineField("icNumber");
const [dateOfBirth] = defineField("dateOfBirth");
const [occupation] = defineField("occupation");
const [employer] = defineField("employer");
const [monthlyIncome] = defineField("monthlyIncome");
const [nationality] = defineField("nationality");

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const updated = await useTenants().update(props.tenant.id, {
      personal: {
        icNumber: values.icNumber || undefined,
        dateOfBirth: values.dateOfBirth || undefined,
        occupation: values.occupation || undefined,
        employer: values.employer || undefined,
        monthlyIncome: ringgitToSen(values.monthlyIncome),
        nationality: values.nationality || undefined,
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
      {{ t("owner.tenants.detail.personalHelp") }}
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <Input
        v-model="icNumber"
        :label="t('owner.tenants.fields.icNumber')"
        :placeholder="t('owner.tenants.placeholders.icNumber')"
        :error="errors.icNumber"
      />
      <Input
        v-model="dateOfBirth"
        type="date"
        :label="t('owner.tenants.fields.dateOfBirth')"
        :error="errors.dateOfBirth"
      />
      <Input
        v-model="occupation"
        :label="t('owner.tenants.fields.occupation')"
        :error="errors.occupation"
      />
      <Input
        v-model="employer"
        :label="t('owner.tenants.fields.employer')"
        :error="errors.employer"
      />
      <Input
        v-model="monthlyIncome"
        type="number"
        step="0.01"
        :min="0"
        :label="t('owner.tenants.fields.monthlyIncome')"
        :error="errors.monthlyIncome"
      />
      <Input
        v-model="nationality"
        :label="t('owner.tenants.fields.nationality')"
        :placeholder="t('owner.tenants.placeholders.nationality')"
        :error="errors.nationality"
      />
    </div>

    <div class="flex justify-end pt-2">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.tenants.detail.save") }}
      </Button>
    </div>
  </form>
</template>
