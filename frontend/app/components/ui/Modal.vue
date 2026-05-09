<script setup lang="ts">
import {
  DialogRoot,
  DialogPortal,
  DialogOverlay,
  DialogContent,
  DialogTitle,
  DialogDescription,
  DialogClose,
} from "reka-ui";
import Icon from "~/components/ui/Icon.vue";

withDefaults(
  defineProps<{
    open: boolean;
    title?: string;
    description?: string;
    size?: "sm" | "md" | "lg";
  }>(),
  { size: "md" },
);

defineEmits<{
  "update:open": [value: boolean];
}>();
</script>

<template>
  <DialogRoot :open="open" @update:open="$emit('update:open', $event)">
    <DialogPortal>
      <DialogOverlay
        class="fixed inset-0 z-40 bg-[rgba(28,28,28,0.40)] backdrop-blur-[2px]"
      />
      <DialogContent
        :class="[
          'fixed left-1/2 top-1/2 z-50 -translate-x-1/2 -translate-y-1/2',
          'flex w-[calc(100vw-2rem)] max-h-[calc(100dvh-2rem)] flex-col',
          'rounded-xl border border-line-passive bg-surface-raised shadow-modal focus:outline-none',
          size === 'sm'
            ? 'max-w-sm'
            : size === 'lg'
              ? 'max-w-2xl'
              : 'max-w-md',
        ]"
      >
        <header
          v-if="title || description"
          class="shrink-0 border-b border-line-passive px-6 pb-4 pt-6 pr-14"
        >
          <DialogTitle
            v-if="title"
            class="text-card-title font-semibold tracking-snug text-ink"
          >
            {{ title }}
          </DialogTitle>
          <DialogDescription
            v-if="description"
            class="mt-1 text-caption text-ink-muted"
          >
            {{ description }}
          </DialogDescription>
        </header>

        <DialogClose
          aria-label="Close"
          class="absolute right-4 top-4 inline-flex h-8 w-8 items-center justify-center rounded-sm text-ink-muted outline-none hover:bg-[rgba(28,28,28,0.04)] focus-visible:shadow-focus"
        >
          <Icon name="X" :size="18" />
        </DialogClose>

        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
          <slot />
        </div>

        <footer
          v-if="$slots.footer"
          class="shrink-0 flex flex-wrap justify-end gap-3 border-t border-line-passive px-6 py-4"
        >
          <slot name="footer" />
        </footer>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
