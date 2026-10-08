<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import Card from "~/components/ui/Card.vue";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";
import { tenantProfileFormSchema } from "~/schemas/tenant";
import type { TenantProfileFormDto } from "~/schemas/tenant";
import type { TenantProfile } from "~/services/useTenants";
import { useToast } from "~/composables/useToast";
import { dateOfBirthFromMyKad, formatMyKadInput, normalizeMyKad } from "~/utils/mykad";

definePageMeta({ layout: "tenant" });
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { formatRM } = useMoney();
const { show } = useToast();
const { toFieldErrors } = useApiError();
const { tenantId } = useTenantSession();
useHead({ title: () => t("tenant.nav.profile") });

const tenant = ref<TenantProfile | null>(null);
const loading = ref(true);
const editing = ref(false);
const saving = ref(false);

const { defineField, handleSubmit, errors, resetForm, setErrors } =
  useForm<TenantProfileFormDto>({
    validationSchema: toTypedSchema(tenantProfileFormSchema),
  });

const [name] = defineField("name");
const [email] = defineField("email");
const [phone] = defineField("phone");
const [icNumber] = defineField("icNumber");
const [dateOfBirth] = defineField("dateOfBirth");
const [occupation] = defineField("occupation");
const [employer] = defineField("employer");
const [monthlyIncomeRm] = defineField("monthlyIncomeRm");
const [nationality] = defineField("nationality");
const [ecName] = defineField("ecName");
const [ecPhone] = defineField("ecPhone");
const [ecRelationship] = defineField("ecRelationship");

const load = async () => {
  if (!tenantId.value) return;
  tenant.value = await useTenants().getProfile(tenantId.value);
};

onMounted(async () => {
  try {
    await load();
  } finally {
    loading.value = false;
  }
  // /tenant/profile?edit=1 (from the home-page nudge) opens the form directly.
  if (route.query.edit === "1" && tenant.value) {
    startEdit();
    router.replace({ query: {} });
  }
});

const fromTenant = (tn: TenantProfile): TenantProfileFormDto => ({
  name: tn.name,
  email: tn.email,
  phone: tn.phone,
  icNumber: tn.personal?.icNumber ?? "",
  dateOfBirth: tn.personal?.dateOfBirth ?? "",
  occupation: tn.personal?.occupation ?? "",
  employer: tn.personal?.employer ?? "",
  monthlyIncomeRm:
    tn.personal?.monthlyIncome != null
      ? tn.personal.monthlyIncome / 100
      : undefined,
  nationality: tn.personal?.nationality ?? "",
  ecName: tn.emergencyContact?.name ?? "",
  ecPhone: tn.emergencyContact?.phone ?? "",
  ecRelationship: tn.emergencyContact?.relationship ?? "",
});

const startEdit = () => {
  if (!tenant.value) return;
  resetForm({ values: fromTenant(tenant.value) });
  editing.value = true;
};

const cancelEdit = () => {
  editing.value = false;
};

// MyKad typed without dashes is formatted live and fills the date of birth.
watch(icNumber, (v) => {
  const formatted = formatMyKadInput(v ?? "");
  if (formatted !== (v ?? "")) {
    icNumber.value = formatted;
    return;
  }
  const dob = dateOfBirthFromMyKad(formatted);
  if (dob) dateOfBirth.value = dob;
});

const onSave = handleSubmit(async (values) => {
  if (!tenantId.value) return;
  saving.value = true;
  try {
    // Email is the login identity — shown read-only, never sent.
    tenant.value = await useTenants().updateProfile(tenantId.value, {
      name: values.name,
      phone: values.phone,
      personal: {
        icNumber: values.icNumber ? (normalizeMyKad(values.icNumber) ?? values.icNumber) : undefined,
        dateOfBirth: values.dateOfBirth || undefined,
        occupation: values.occupation || undefined,
        employer: values.employer || undefined,
        monthlyIncome:
          values.monthlyIncomeRm != null
            ? Math.round(values.monthlyIncomeRm * 100)
            : undefined,
        nationality: values.nationality || undefined,
      },
      emergencyContact: {
        name: values.ecName || undefined,
        phone: values.ecPhone || undefined,
        relationship: values.ecRelationship || undefined,
      },
    });
    editing.value = false;
    show(t("tenant.profile.savedToast"), "success");
  } catch (err) {
    const fieldErrors = toFieldErrors(err);
    if (fieldErrors) {
      setErrors(fieldErrors);
      return;
    }
    show(t("common.genericError"), "danger");
  } finally {
    saving.value = false;
  }
});

const formatDate = (iso?: string) => {
  if (!iso) return null;
  const [y, m, d] = iso.split("-");
  return `${d}/${m}/${y}`;
};
</script>

<template>
  <div>
    <header
      class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-start sm:justify-between"
    >
      <div>
        <h1 class="text-display-sub font-semibold tracking-snug">
          {{ t("tenant.nav.profile") }}
        </h1>
        <p class="mt-1 text-caption text-ink-muted">
          {{ t("tenant.profile.subtitle") }}
        </p>
      </div>
      <Button
        v-if="tenant && !editing"
        variant="ghost"
        size="sm"
        class="self-start"
        @click="startEdit"
      >
        <Icon name="Pencil" :size="14" class="mr-1.5" />
        {{ t("tenant.profile.edit") }}
      </Button>
    </header>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <template v-else-if="tenant">
      <!-- VIEW MODE -->
      <div v-if="!editing" class="space-y-4 sm:space-y-6">
        <Card padding="loose">
          <h2 class="mb-4 text-card-title font-semibold text-ink">
            {{ t("tenant.profile.sections.identity") }}
          </h2>
          <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.name") }}</dt>
              <dd class="mt-0.5 text-body text-ink">{{ tenant.name }}</dd>
            </div>
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.email") }}</dt>
              <dd class="mt-0.5 text-body text-ink">{{ tenant.email }}</dd>
            </div>
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.phone") }}</dt>
              <dd class="mt-0.5 text-body text-ink tabular-nums">{{ tenant.phone }}</dd>
            </div>
          </dl>
        </Card>

        <Card padding="loose">
          <h2 class="mb-4 text-card-title font-semibold text-ink">
            {{ t("tenant.profile.sections.personal") }}
          </h2>
          <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.icNumber") }}</dt>
              <dd class="mt-0.5 text-body tabular-nums" :class="tenant.personal?.icNumber ? 'text-ink' : 'text-ink-faint'">
                {{ tenant.personal?.icNumber ?? t("tenant.profile.notSet") }}
              </dd>
            </div>
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.dateOfBirth") }}</dt>
              <dd class="mt-0.5 text-body tabular-nums" :class="tenant.personal?.dateOfBirth ? 'text-ink' : 'text-ink-faint'">
                {{ formatDate(tenant.personal?.dateOfBirth) ?? t("tenant.profile.notSet") }}
              </dd>
            </div>
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.occupation") }}</dt>
              <dd class="mt-0.5 text-body" :class="tenant.personal?.occupation ? 'text-ink' : 'text-ink-faint'">
                {{ tenant.personal?.occupation ?? t("tenant.profile.notSet") }}
              </dd>
            </div>
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.employer") }}</dt>
              <dd class="mt-0.5 text-body" :class="tenant.personal?.employer ? 'text-ink' : 'text-ink-faint'">
                {{ tenant.personal?.employer ?? t("tenant.profile.notSet") }}
              </dd>
            </div>
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.monthlyIncome") }}</dt>
              <dd class="mt-0.5 text-body tabular-nums" :class="tenant.personal?.monthlyIncome != null ? 'text-ink' : 'text-ink-faint'">
                {{ tenant.personal?.monthlyIncome != null ? formatRM(tenant.personal.monthlyIncome) : t("tenant.profile.notSet") }}
              </dd>
            </div>
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.nationality") }}</dt>
              <dd class="mt-0.5 text-body" :class="tenant.personal?.nationality ? 'text-ink' : 'text-ink-faint'">
                {{ tenant.personal?.nationality ?? t("tenant.profile.notSet") }}
              </dd>
            </div>
          </dl>
        </Card>

        <Card padding="loose">
          <h2 class="mb-4 text-card-title font-semibold text-ink">
            {{ t("tenant.profile.sections.emergency") }}
          </h2>
          <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.ecName") }}</dt>
              <dd class="mt-0.5 text-body" :class="tenant.emergencyContact?.name ? 'text-ink' : 'text-ink-faint'">
                {{ tenant.emergencyContact?.name ?? t("tenant.profile.notSet") }}
              </dd>
            </div>
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.ecPhone") }}</dt>
              <dd class="mt-0.5 text-body tabular-nums" :class="tenant.emergencyContact?.phone ? 'text-ink' : 'text-ink-faint'">
                {{ tenant.emergencyContact?.phone ?? t("tenant.profile.notSet") }}
              </dd>
            </div>
            <div>
              <dt class="text-caption text-ink-muted">{{ t("tenant.profile.fields.ecRelationship") }}</dt>
              <dd class="mt-0.5 text-body" :class="tenant.emergencyContact?.relationship ? 'text-ink' : 'text-ink-faint'">
                {{ tenant.emergencyContact?.relationship ?? t("tenant.profile.notSet") }}
              </dd>
            </div>
          </dl>
        </Card>
      </div>

      <!-- EDIT MODE -->
      <form v-else class="space-y-4 sm:space-y-6" @submit.prevent="onSave">
        <Card padding="loose">
          <h2 class="mb-4 text-card-title font-semibold text-ink">
            {{ t("tenant.profile.sections.identity") }}
          </h2>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input v-model="name" :label="t('tenant.profile.fields.name')" :error="errors.name" />
            <Input v-model="email" type="email" :label="t('tenant.profile.fields.email')" :error="errors.email" disabled />
            <Input v-model="phone" :label="t('tenant.profile.fields.phone')" :error="errors.phone" />
          </div>
        </Card>

        <Card padding="loose">
          <h2 class="mb-4 text-card-title font-semibold text-ink">
            {{ t("tenant.profile.sections.personal") }}
          </h2>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input v-model="icNumber" :label="t('tenant.profile.fields.icNumber')" :placeholder="t('tenant.profile.placeholders.icNumber')" :error="errors.icNumber" />
            <Input v-model="dateOfBirth" type="date" :label="t('tenant.profile.fields.dateOfBirth')" :error="errors.dateOfBirth" />
            <Input v-model="occupation" :label="t('tenant.profile.fields.occupation')" :error="errors.occupation" />
            <Input v-model="employer" :label="t('tenant.profile.fields.employer')" :error="errors.employer" />
            <Input v-model="monthlyIncomeRm" type="number" :min="0" :step="100" :label="t('tenant.profile.fields.monthlyIncome')" :error="errors.monthlyIncomeRm">
              <template #suffix>
                <span class="text-caption text-ink-muted">RM</span>
              </template>
            </Input>
            <Input v-model="nationality" :label="t('tenant.profile.fields.nationality')" :error="errors.nationality" />
          </div>
          <p class="mt-3 text-micro text-ink-faint">
            {{ t("tenant.profile.incomeHint") }}
          </p>
        </Card>

        <Card padding="loose">
          <h2 class="mb-4 text-card-title font-semibold text-ink">
            {{ t("tenant.profile.sections.emergency") }}
          </h2>
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Input v-model="ecName" :label="t('tenant.profile.fields.ecName')" :error="errors.ecName" />
            <Input v-model="ecPhone" :label="t('tenant.profile.fields.ecPhone')" :error="errors.ecPhone" />
            <Input v-model="ecRelationship" :label="t('tenant.profile.fields.ecRelationship')" :error="errors.ecRelationship" />
          </div>
        </Card>

        <div class="flex justify-end gap-2">
          <Button type="button" variant="ghost" :disabled="saving" @click="cancelEdit">
            {{ t("tenant.profile.cancel") }}
          </Button>
          <Button type="submit" variant="primary" :loading="saving">
            {{ t("tenant.profile.save") }}
          </Button>
        </div>
      </form>
    </template>
  </div>
</template>
