<script setup lang="ts">
import HeroSection from "~/components/marketing/HeroSection.vue";
import UspShowcase from "~/components/marketing/UspShowcase.vue";
import EmailCapture from "~/components/marketing/EmailCapture.vue";

definePageMeta({ layout: "marketing" });

const { t } = useI18n();
useHead({
  title: () => `${t("marketing.hero.eyebrow")} · Roofly.my`,
  meta: [
    { name: "description", content: () => t("marketing.hero.subhead") },
  ],
});

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
