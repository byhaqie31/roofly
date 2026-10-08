<script setup lang="ts">
import { ref, watch } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import { tenantInputSchema } from "~/schemas/tenant";
import type { Tenant, TenantInput } from "~/types/tenant";
import type { TenantInviteResult } from "~/services/contracts/tenants";
import { useToast } from "~/composables/useToast";
import { copyToClipboard } from "~/utils/clipboard";
import { whatsappShareUrl } from "~/utils/whatsapp";
import Modal from "~/components/ui/Modal.vue";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{
  "update:open": [value: boolean];
  invited: [tenant: Tenant];
}>();

const { t } = useI18n();
const { show } = useToast();
const submitting = ref(false);

// After a successful invite the modal stays open on a "sent" panel with the
// same link the email carries, so the owner can copy / WhatsApp it as a
// backup (spec 2026-10-07 § 4.3). The link is only known at this moment —
// the backend stores a hash — which is why it's shown here and not later.
const result = ref<TenantInviteResult | null>(null);

const initialValues: TenantInput = { name: "", email: "", phone: "" };

const { defineField, handleSubmit, errors, resetForm, setErrors } = useForm<TenantInput>({
  validationSchema: toTypedSchema(tenantInputSchema),
  initialValues,
});
const { toFieldErrors } = useApiError();

const [name] = defineField("name");
const [email] = defineField("email");
const [phone] = defineField("phone");

const onSubmit = handleSubmit(async (values) => {
  submitting.value = true;
  try {
    const res = await useTenants().invite(values);
    result.value = res;
    show(t("owner.tenants.invitedToast"), "success");
    emit("invited", res.tenant);
  } catch (err) {
    const fieldErrors = toFieldErrors(err);
    if (fieldErrors) {
      setErrors(fieldErrors);
      return;
    }
    show(t("common.genericError"), "danger");
  } finally {
    submitting.value = false;
  }
});

const copyLink = async () => {
  if (!result.value) return;
  const ok = await copyToClipboard(result.value.inviteUrl);
  show(t(ok ? "owner.tenants.linkCopied" : "owner.tenants.copyFailed"), ok ? "success" : "danger");
};

const whatsappHref = () =>
  result.value
    ? whatsappShareUrl(
        result.value.tenant.phone,
        t("owner.tenants.whatsappMessage", { name: result.value.tenant.name, url: result.value.inviteUrl }),
      )
    : "#";

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      result.value = null;
      resetForm({ values: initialValues });
    }
  },
);
</script>

<template>
  <Modal
    :open="open"
    :title="result ? t('owner.tenants.inviteSentTitle', { email: result.tenant.email }) : t('owner.tenants.inviteTitle')"
    size="md"
    @update:open="emit('update:open', $event)"
  >
    <!-- Sent: the backup link -->
    <div v-if="result" class="space-y-4">
      <p class="text-caption text-ink-muted">
        {{ t("owner.tenants.inviteSentHelp") }}
      </p>
      <div>
        <p class="mb-1 text-caption font-medium text-ink">{{ t("owner.tenants.inviteLinkLabel") }}</p>
        <p
          class="select-all break-all rounded-md border border-line-passive bg-surface-raised px-3 py-2 font-mono text-caption text-ink"
          data-testid="invite-link"
        >
          {{ result.inviteUrl }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <Button variant="cream" size="sm" @click="copyLink">
          <Icon name="Copy" :size="14" class="mr-1" />
          {{ t("owner.tenants.copyLink") }}
        </Button>
        <a
          :href="whatsappHref()"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center rounded-pill border border-line-passive px-4 py-2 text-caption font-medium text-ink transition hover:bg-surface-hover focus:outline-none focus-visible:shadow-focus"
        >
          <Icon name="MessageCircle" :size="14" class="mr-1" />
          {{ t("owner.tenants.shareWhatsApp") }}
        </a>
      </div>
    </div>

    <!-- Form -->
    <form
      v-else
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
      <template v-if="result">
        <Button variant="primary" @click="emit('update:open', false)">
          {{ t("common.close") }}
        </Button>
      </template>
      <template v-else>
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
    </template>
  </Modal>
</template>
