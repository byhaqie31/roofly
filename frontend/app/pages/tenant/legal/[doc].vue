<script setup lang="ts">
import LegalDocument from "~/components/legal/LegalDocument.vue";
import type { LegalSlug } from "~/config/legal";
import { isLegalSlugAvailable } from "~/utils/legal";

// In-app copy of the legal documents for the tenant shell, so reading them
// never drops out of the app. Same content + 404 rule as the public
// pages/legal/[doc].vue; never tracked (it's inside /tenant).
definePageMeta({
  layout: "tenant",
  key: (route) => route.path,
  validate: (route) => isLegalSlugAvailable(String(route.params.doc), { showBetaTerms: useEnv().showBetaTerms }),
});

const route = useRoute();
const { t } = useI18n();
const slug = String(route.params.doc) as LegalSlug;
useHead({ title: () => t(`legal.docs.${slug}`) });
</script>

<template>
  <LegalDocument :slug="slug" base-path="/tenant/legal" embedded />
</template>
