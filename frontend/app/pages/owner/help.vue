<script setup lang="ts">
import HelpPage from "~/components/layout/HelpPage.vue";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("help.title") });
</script>

<template>
  <HelpPage audience="owner" />
</template>
