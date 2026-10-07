<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useForm } from "vee-validate";
import { toTypedSchema } from "@vee-validate/zod";
import Button from "~/components/ui/Button.vue";
import Input from "~/components/ui/Input.vue";
import { tenantOnboardingSchema, TENANT_ONBOARDING_STEPS } from "~/schemas/tenant";
import type { TenantOnboardingDto } from "~/schemas/tenant";
import type { TenantProfileUpdate } from "~/services/contracts/tenants";
import { useToast } from "~/composables/useToast";
import { dateOfBirthFromMyKad, formatMyKadInput, normalizeMyKad } from "~/utils/mykad";

/**
 * Tenant first-run (spec 2026-10-07 § 4.4). Reached only via the route guard
 * when `auth.user.onboardedAt` is empty — i.e. right after accepting the
 * invite. Three short steps; each validates its own fields before advancing;
 * Finish saves the profile and stamps onboarding in one call. No skip: the
 * core fields are what the landlord needs for the agreement.
 */
definePageMeta({ layout: "onboarding" });

const { t } = useI18n();
useHead({ title: () => t("tenant.onboarding.title") });
const { show } = useToast();
const { toFieldErrors } = useApiError();
const auth = useAuthStore();
const { tenantId } = useTenantSession();

const steps = TENANT_ONBOARDING_STEPS;
const stepIndex = ref(0);
const step = computed(() => steps[stepIndex.value]!);
const isLast = computed(() => stepIndex.value === steps.length - 1);
const submitting = ref(false);

const { defineField, handleSubmit, errors, resetForm, setErrors, validateField } =
  useForm<TenantOnboardingDto>({ validationSchema: toTypedSchema(tenantOnboardingSchema) });

const [name] = defineField("name");
const [phone] = defineField("phone");
const [icNumber] = defineField("icNumber");
const [dateOfBirth] = defineField("dateOfBirth");
const [nationality] = defineField("nationality");
const [occupation] = defineField("occupation");
const [employer] = defineField("employer");
const [monthlyIncomeRm] = defineField("monthlyIncomeRm");
const [ecName] = defineField("ecName");
const [ecPhone] = defineField("ecPhone");
const [ecRelationship] = defineField("ecRelationship");

onMounted(async () => {
  // Prefill what the owner typed when inviting (name, phone) — never make the
  // tenant retype it. Email isn't shown: it's the login identity, not editable.
  const profile = tenantId.value ? await useTenants().getProfile(tenantId.value) : null;
  resetForm({
    values: {
      name: profile?.name ?? auth.user?.name ?? "",
      phone: profile?.phone ?? auth.user?.phone ?? "",
      icNumber: profile?.personal?.icNumber ?? "",
      dateOfBirth: profile?.personal?.dateOfBirth ?? "",
      nationality: profile?.personal?.nationality ?? "Malaysian",
      occupation: profile?.personal?.occupation ?? "",
      employer: profile?.personal?.employer ?? "",
      monthlyIncomeRm: profile?.personal?.monthlyIncome != null ? profile.personal.monthlyIncome / 100 : undefined,
      ecName: profile?.emergencyContact?.name ?? "",
      ecPhone: profile?.emergencyContact?.phone ?? "",
      ecRelationship: profile?.emergencyContact?.relationship ?? "",
    },
  });
});

// MyKad: no dashes to type — format live, and the first six digits ARE the
// birth date, so fill it in (it stays editable for the rare mismatch).
watch(icNumber, (v) => {
  const formatted = formatMyKadInput(v ?? "");
  if (formatted !== (v ?? "")) {
    icNumber.value = formatted;
    return;
  }
  const dob = dateOfBirthFromMyKad(formatted);
  if (dob) dateOfBirth.value = dob;
});

const next = async () => {
  const results = await Promise.all(step.value.fields.map((f) => validateField(f)));
  if (results.every((r) => r.valid)) stepIndex.value += 1;
};
const back = () => {
  if (stepIndex.value > 0) stepIndex.value -= 1;
};

const blank = (v: string | undefined) => (v && v.trim() !== "" ? v : undefined);

// API validation keys are nested (personal.icNumber); map them back onto the flat form.
const FIELD_FROM_API: Record<string, keyof TenantOnboardingDto> = {
  "personal.icNumber": "icNumber",
  "personal.dateOfBirth": "dateOfBirth",
  "personal.nationality": "nationality",
  "personal.occupation": "occupation",
  "personal.employer": "employer",
  "personal.monthlyIncome": "monthlyIncomeRm",
  "emergencyContact.name": "ecName",
  "emergencyContact.phone": "ecPhone",
  "emergencyContact.relationship": "ecRelationship",
};
const STEP_OF_FIELD = Object.fromEntries(
  steps.flatMap((s, i) => s.fields.map((f) => [f, i])),
) as Record<keyof TenantOnboardingDto, number>;

const finish = handleSubmit(async (v) => {
  if (!tenantId.value) return;
  submitting.value = true;
  try {
    const patch: TenantProfileUpdate = {
      name: v.name,
      phone: v.phone,
      personal: {
        icNumber: normalizeMyKad(v.icNumber) ?? v.icNumber,
        dateOfBirth: blank(v.dateOfBirth),
        nationality: blank(v.nationality),
        occupation: blank(v.occupation),
        employer: blank(v.employer),
        monthlyIncome: v.monthlyIncomeRm != null ? Math.round(v.monthlyIncomeRm * 100) : undefined,
      },
      emergencyContact: { name: v.ecName, phone: v.ecPhone, relationship: blank(v.ecRelationship) },
    };
    const user = await useTenants().completeOnboarding(tenantId.value, patch);
    auth.setUser(user);
    await navigateTo("/tenant");
  } catch (err) {
    const apiErrors = toFieldErrors(err);
    if (apiErrors) {
      const mapped: Partial<Record<keyof TenantOnboardingDto, string>> = {};
      for (const [key, msg] of Object.entries(apiErrors)) {
        const field = FIELD_FROM_API[key] ?? (key as keyof TenantOnboardingDto);
        mapped[field] = msg;
      }
      setErrors(mapped);
      // Jump back to the earliest step that has a problem.
      const firstBad = Math.min(...Object.keys(mapped).map((f) => STEP_OF_FIELD[f as keyof TenantOnboardingDto] ?? 0));
      stepIndex.value = Number.isFinite(firstBad) ? firstBad : 0;
      return;
    }
    show(t("common.genericError"), "danger");
  } finally {
    submitting.value = false;
  }
});
</script>

<template>
  <div>
    <header class="mb-8 text-center">
      <p class="mb-2 text-micro font-semibold uppercase tracking-wide text-ink-muted">
        {{ t("tenant.onboarding.step", { n: stepIndex + 1, total: steps.length }) }}
      </p>
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ stepIndex === 0 ? t("tenant.onboarding.title") : t(`tenant.onboarding.steps.${step.key}.title`) }}
      </h1>
      <p class="mt-2 text-body text-ink-muted">
        {{ stepIndex === 0 ? t("tenant.onboarding.subtitle") : t(`tenant.onboarding.steps.${step.key}.hint`) }}
      </p>
    </header>

    <!-- Progress: one segment per step -->
    <div class="mb-6 flex gap-1.5" aria-hidden="true">
      <span
        v-for="(s, i) in steps"
        :key="s.key"
        class="h-1 flex-1 rounded-pill transition-colors"
        :class="i <= stepIndex ? 'bg-ink' : 'bg-line-passive'"
      />
    </div>

    <form class="space-y-4" @submit.prevent="isLast ? finish() : next()">
      <template v-if="step.key === 'about'">
        <Input v-model="name" :label="t('tenant.profile.fields.name')" autocomplete="name" :error="errors.name" size="lg" />
        <Input v-model="phone" type="tel" :label="t('tenant.profile.fields.phone')" autocomplete="tel" :error="errors.phone" size="lg" />
        <Input
          v-model="icNumber"
          :label="t('tenant.profile.fields.icNumber')"
          :placeholder="t('tenant.profile.placeholders.icNumber')"
          inputmode="numeric"
          :error="errors.icNumber"
          size="lg"
        />
        <div class="grid gap-4 sm:grid-cols-2">
          <Input v-model="dateOfBirth" type="date" :label="`${t('tenant.profile.fields.dateOfBirth')} · ${t('tenant.onboarding.optional')}`" :error="errors.dateOfBirth" size="lg" />
          <Input v-model="nationality" :label="t('tenant.profile.fields.nationality')" :error="errors.nationality" size="lg" />
        </div>
      </template>

      <template v-else-if="step.key === 'work'">
        <Input v-model="occupation" :label="t('tenant.profile.fields.occupation')" :error="errors.occupation" size="lg" />
        <Input v-model="employer" :label="t('tenant.profile.fields.employer')" :error="errors.employer" size="lg" />
        <Input
          v-model="monthlyIncomeRm"
          type="number"
          :min="0"
          :step="100"
          inputmode="decimal"
          :label="`${t('tenant.profile.fields.monthlyIncome')} (RM)`"
          :error="errors.monthlyIncomeRm"
          size="lg"
        />
      </template>

      <template v-else>
        <Input v-model="ecName" :label="t('tenant.profile.fields.ecName')" :error="errors.ecName" size="lg" />
        <Input v-model="ecPhone" type="tel" :label="t('tenant.profile.fields.ecPhone')" :error="errors.ecPhone" size="lg" />
        <Input v-model="ecRelationship" :label="`${t('tenant.profile.fields.ecRelationship')} · ${t('tenant.onboarding.optional')}`" :error="errors.ecRelationship" size="lg" />
      </template>

      <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Button
          type="button"
          variant="ghost"
          size="lg"
          :disabled="stepIndex === 0 || submitting"
          :class="stepIndex === 0 ? 'invisible' : ''"
          @click="back"
        >
          {{ t("tenant.onboarding.back") }}
        </Button>
        <Button type="submit" variant="primary" size="lg" :loading="submitting" class="sm:min-w-56">
          {{ isLast ? t("tenant.onboarding.finish") : t("tenant.onboarding.continue") }}
        </Button>
      </div>
    </form>
  </div>
</template>
