<script setup lang="ts">
import HeroSection from "~/components/marketing/HeroSection.vue";
import UspShowcase from "~/components/marketing/UspShowcase.vue";
import EmailCapture from "~/components/marketing/EmailCapture.vue";

definePageMeta({ layout: "marketing" });

const { t, locale } = useI18n();
const siteUrl = useRuntimeConfig().public.siteUrl as string;
const ogImage = `${siteUrl}/marketing/og.png`;
const pageUrl = `${siteUrl}/coming-soon`;

// Social preview (Open Graph + Twitter card). The image lives at
// public/marketing/og.png (1200x630) — regenerate it if the hero copy changes.
useSeoMeta({
  title: () => `${t("marketing.hero.eyebrow")} · Roofly.my`,
  description: () => t("marketing.hero.subhead"),
  ogType: "website",
  ogSiteName: "Roofly.my",
  ogTitle: () => `${t("marketing.hero.headlineLead")} ${t("marketing.hero.headlineWords.0")}`,
  ogDescription: () => t("marketing.hero.subhead"),
  ogUrl: pageUrl,
  ogImage,
  ogImageWidth: 1200,
  ogImageHeight: 630,
  ogImageAlt: "Roofly.my — property management, reimagined. Coming soon.",
  ogLocale: () => (locale.value === "ms" ? "ms_MY" : "en_MY"),
  twitterCard: "summary_large_image",
  twitterTitle: () => `${t("marketing.hero.headlineLead")} ${t("marketing.hero.headlineWords.0")}`,
  twitterDescription: () => t("marketing.hero.subhead"),
  twitterImage: ogImage,
});
useHead({ link: [{ rel: "canonical", href: pageUrl }] });

const scrollToCapture = () => {
  if (typeof document === "undefined") return;
  const target = document.getElementById("email-capture");
  target?.scrollIntoView({ behavior: "smooth", block: "start" });
};
</script>

<template>
  <div>
    <HeroSection @scroll-to-capture="scrollToCapture" />
    <UspShowcase />
    <EmailCapture />
  </div>
</template>
