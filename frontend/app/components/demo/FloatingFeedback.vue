<script setup lang="ts">
import { MessageSquare } from "lucide-vue-next";

/**
 * Fixed-position feedback button visible across every demo route.
 * Opens NUXT_PUBLIC_DEMO_FEEDBACK_URL (a Google Form) in a new tab.
 *
 * Visibility is gated by useEnv().showFloatingFeedback at the mount site
 * (app.vue). When the URL is unset, the gate returns false and this component
 * is never rendered.
 */
const config = useRuntimeConfig();
const url = config.public.demoFeedbackUrl as string;
</script>

<template>
  <a
    :href="url"
    target="_blank"
    rel="noopener noreferrer"
    aria-label="Share feedback"
    data-tour="feedback"
    class="fixed bottom-6 right-6 z-50 inline-flex items-center gap-2 rounded-full bg-ink px-5 py-3 text-caption font-medium text-surface-page shadow-lg transition hover:bg-ink-strong focus:outline-none focus-visible:ring-2 focus-visible:ring-ink focus-visible:ring-offset-2"
  >
    <MessageSquare :size="18" :stroke-width="1.5" />
    <span>Share feedback</span>
  </a>
</template>
