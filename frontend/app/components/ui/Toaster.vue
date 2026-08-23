<script setup lang="ts">
import { CheckCircle2, AlertCircle, Info } from "lucide-vue-next";
import { useToast } from "~/composables/useToast";

const { toasts } = useToast();

const icons = { success: CheckCircle2, danger: AlertCircle, default: Info } as const;
</script>

<template>
  <!-- Top-centre (same height as the old top-right stack), filled (solid)
       tone so it reads at a glance.
       Each toast wiggles once on entry (CSS keyframes, reduced-motion aware). -->
  <div
    class="pointer-events-none fixed top-20 inset-x-0 z-[100] flex flex-col items-center gap-3 px-4"
  >
    <TransitionGroup
      enter-active-class="toast-enter"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-for="t in toasts"
        :key="t.id"
        :class="[
          'pointer-events-auto inline-flex items-center gap-3 min-w-[260px] max-w-md rounded-md px-5 py-3.5 text-body font-medium shadow-modal',
          t.tone === 'success'
            ? 'bg-status-active text-white'
            : t.tone === 'danger'
              ? 'bg-status-overdue text-white'
              : 'bg-surface-dark text-surface-ondark',
        ]"
        role="status"
      >
        <component :is="icons[t.tone]" :size="20" :stroke-width="2" class="shrink-0" />
        <span>{{ t.message }}</span>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
@keyframes toast-wiggle {
  0%   { opacity: 0; transform: translateY(-10px) scale(0.9) rotate(0deg); }
  20%  { opacity: 1; transform: translateY(0) scale(1.04) rotate(-3deg); }
  40%  { transform: scale(1.02) rotate(3deg); }
  60%  { transform: scale(1.01) rotate(-2deg); }
  80%  { transform: scale(1) rotate(1.5deg); }
  100% { transform: scale(1) rotate(0deg); }
}

.toast-enter {
  animation: toast-wiggle 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@media (prefers-reduced-motion: reduce) {
  .toast-enter {
    animation: none;
  }
}
</style>
