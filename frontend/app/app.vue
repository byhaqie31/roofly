<script setup lang="ts">
import Toaster from "~/components/ui/Toaster.vue";
import FloatingFeedback from "~/components/demo/FloatingFeedback.vue";
import EnvBanner from "~/components/layout/EnvBanner.vue";

const { showFloatingFeedback, showEnvBanner } = useEnv();
</script>

<template>
  <div>
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
    <Toaster />
    <FloatingFeedback v-if="showFloatingFeedback" />
    <EnvBanner v-if="showEnvBanner" />
  </div>
</template>
