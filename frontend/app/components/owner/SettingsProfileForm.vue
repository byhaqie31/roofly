<script setup lang="ts">
import { ref } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { ownerProfileFormSchema } from "~/schemas/owner";
import type { OwnerAccount } from "~/types/owner";
import { useToast } from "~/composables/useToast";
import Card from "~/components/ui/Card.vue";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";
import EmptyState from "~/components/ui/EmptyState.vue";

const props = defineProps<{ account: OwnerAccount }>();
const emit = defineEmits<{ saved: [account: OwnerAccount] }>();

const { t } = useI18n();
const { show } = useToast();
const { public: { features } } = useRuntimeConfig();
const photosEnabled = features.documents;
const submitting = ref(false);

const initialValues = {
  name: props.account.profile.name,
  phone: props.account.profile.phone,
  businessName: props.account.profile.businessName ?? "",
};

const { defineField, handleSubmit, errors } = useForm({
  validationSchema: toTypedSchema(ownerProfileFormSchema),
  initialValues,
});

const [name] = defineField("name");
const [phone] = defineField("phone");
const [businessName] = defineField("businessName");

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const updated = await useOwnerSettings().updateProfile({
      name: values.name,
      phone: values.phone,
      businessName: values.businessName || undefined,
    });
    emit("saved", updated);
    show(t("common.savedToast"), "success");
  } finally {
    submitting.value = false;
  }
});
</script>

<template>
  <form class="space-y-6" @submit.prevent="onSubmit">
    <Card padding="loose" tone="flat">
      <header class="mb-4">
        <h2 class="text-card-title font-semibold text-ink">
          {{ t("owner.settings.profile.identity") }}
        </h2>
        <p class="mt-1 text-caption text-ink-muted">
          {{ t("owner.settings.profile.identityHelp") }}
        </p>
      </header>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input
          v-model="name"
          :label="t('owner.settings.profile.fields.name')"
          :error="errors.name"
        />
        <div>
          <Input
            :model-value="account.profile.email"
            :label="t('owner.settings.profile.fields.email')"
            disabled
          />
          <p class="mt-1.5 text-micro text-ink-faint">
            {{ t("owner.settings.profile.emailManagedNote") }}
          </p>
        </div>
        <Input
          v-model="phone"
          type="tel"
          :label="t('owner.settings.profile.fields.phone')"
          :error="errors.phone"
        />
        <Input
          v-model="businessName"
          :label="t('owner.settings.profile.fields.businessName')"
          :placeholder="t('owner.settings.profile.placeholders.businessName')"
          :error="errors.businessName"
        />
      </div>
    </Card>

    <Card padding="loose" tone="flat">
      <header class="mb-4">
        <h2 class="text-card-title font-semibold text-ink">
          {{ t("owner.settings.profile.photo") }}
        </h2>
        <p class="mt-1 text-caption text-ink-muted">
          {{ t("owner.settings.profile.photoHelp") }}
        </p>
      </header>
      <EmptyState
        v-if="photosEnabled"
        icon="Camera"
        :title="t('owner.settings.profile.photoPlaceholderTitle')"
        :description="t('owner.settings.profile.photoPlaceholderHelp')"
      />
      <p v-else class="text-caption text-ink-muted">
        {{ t("owner.settings.profile.photoHelp") }}
      </p>
    </Card>

    <Card v-if="account.profile.bankAccountLast4" padding="loose">
      <header class="mb-4">
        <h2 class="text-card-title font-semibold text-ink">
          {{ t("owner.settings.profile.payout") }}
        </h2>
        <p class="mt-1 text-caption text-ink-muted">
          {{ t("owner.settings.profile.payoutHelp") }}
        </p>
      </header>
      <p class="text-body text-ink">
        {{ t("owner.settings.profile.bankMasked") }}
        <span class="font-semibold tabular-nums">
          •••• {{ account.profile.bankAccountLast4 }}
        </span>
      </p>
    </Card>

    <div class="flex justify-end">
      <Button type="submit" variant="primary" :loading="submitting">
        {{ t("owner.settings.save") }}
      </Button>
    </div>
  </form>
</template>
