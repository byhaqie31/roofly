<script setup lang="ts">
import { computed } from "vue";
import LegalDocument from "~/components/legal/LegalDocument.vue";
import type { LegalSlug } from "~/config/legal";
import { getLegalDocument } from "~/content/legal";
import { isLegalSlugAvailable } from "~/utils/legal";

// /legal/privacy, /legal/terms, /legal/billing, /legal/acceptable-use and —
// on UAT only (useEnv().showBetaTerms) — /legal/beta. Anything else 404s via
// `validate`. Public in every environment, including production while
// comingSoonOnly is on (utils/comingSoonGate.ts).
definePageMeta({
  layout: "legal",
  key: (route) => route.path,
  validate: (route) => isLegalSlugAvailable(String(route.params.doc), { showBetaTerms: useEnv().showBetaTerms }),
});

const route = useRoute();
const { t, locale } = useI18n();
const slug = String(route.params.doc) as LegalSlug;

const doc = computed(() => getLegalDocument(slug, locale.value));
const siteUrl = useRuntimeConfig().public.siteUrl as string;

useSeoMeta({
  title: () => t(`legal.docs.${slug}`),
  description: () => doc.value.summary.replace(/\[([^\]]+)\]\([^)]+\)/g, "$1"),
  ogTitle: () => t(`legal.docs.${slug}`),
  ogUrl: `${siteUrl}/legal/${slug}`,
});
useHead({
  htmlAttrs: { lang: () => (locale.value === "ms" ? "ms" : "en") },
  link: [{ rel: "canonical", href: `${siteUrl}/legal/${slug}` }],
});
</script>

<template>
  <LegalDocument :slug="slug" />
</template>
