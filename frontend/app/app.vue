<script setup lang="ts">
import Toaster from "~/components/ui/Toaster.vue";
import FloatingFeedback from "~/components/demo/FloatingFeedback.vue";

const { showFloatingFeedback } = useEnv();
</script>

<template>
  <div>
    <NuxtRouteAnnouncer />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
    <Toaster />
    <FloatingFeedback v-if="showFloatingFeedback" />
  </div>
</template>
