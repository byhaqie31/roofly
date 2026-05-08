<script setup lang="ts">
import { useToast } from "~/composables/useToast";
const { toasts } = useToast();
</script>

<template>
  <div
    class="pointer-events-none fixed bottom-6 right-6 z-[100] flex flex-col gap-2"
  >
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="t in toasts"
        :key="t.id"
        :class="[
          'pointer-events-auto min-w-[240px] max-w-md rounded-md border px-4 py-3 text-body shadow-modal',
          t.tone === 'success'
            ? 'bg-status-active-soft border-status-active text-status-active'
            : t.tone === 'danger'
              ? 'bg-status-overdue-soft border-status-overdue text-status-overdue'
              : 'bg-surface-raised border-line-passive text-ink',
        ]"
        role="status"
      >
        {{ t.message }}
      </div>
    </TransitionGroup>
  </div>
</template>
