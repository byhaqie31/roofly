<script setup lang="ts">
import { House, X } from "lucide-vue-next";
import { watch } from "vue";

const props = defineProps<{ modelValue: boolean; homeTo?: string }>();
const emit = defineEmits<{ "update:modelValue": [value: boolean] }>();

const route = useRoute();

// Auto-close on route change
watch(
  () => route.fullPath,
  () => emit("update:modelValue", false),
);

// Lock body scroll while drawer is open
watch(
  () => props.modelValue,
  (open) => {
    if (!import.meta.client) return;
    document.body.style.overflow = open ? "hidden" : "";
  },
);

// Close on escape
if (import.meta.client) {
  watch(
    () => props.modelValue,
    (open) => {
      const onKey = (e: KeyboardEvent) => {
        if (e.key === "Escape") emit("update:modelValue", false);
      };
      if (open) window.addEventListener("keydown", onKey);
      else window.removeEventListener("keydown", onKey);
    },
  );
}
</script>

<template>
  <Teleport to="body">
    <Transition name="backdrop">
      <div
        v-if="modelValue"
        class="md:hidden fixed inset-0 bg-[rgba(28,28,28,0.5)] z-40"
        aria-hidden="true"
        @click="emit('update:modelValue', false)"
      />
    </Transition>
    <Transition name="drawer">
      <aside
        v-if="modelValue"
        class="md:hidden fixed inset-y-0 left-0 w-72 max-w-[85vw] bg-surface-page border-r border-line-passive z-50 flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-label="Navigation"
      >
        <header class="flex items-center justify-between px-4 py-4 border-b border-line-passive">
          <NuxtLink
            :to="homeTo ?? '/'"
            class="inline-flex items-center gap-2 text-card-title font-semibold tracking-tight"
          >
            <House :size="20" :stroke-width="1.75" class="text-accent" />
            <span>Roofly.my</span>
          </NuxtLink>
          <button
            type="button"
            class="inline-flex items-center justify-center w-9 h-9 rounded-sm text-ink-strong hover:bg-[rgba(28,28,28,0.04)] focus-visible:shadow-focus transition"
            aria-label="Close menu"
            @click="emit('update:modelValue', false)"
          >
            <X :size="20" :stroke-width="1.5" />
          </button>
        </header>
        <div class="flex-1 overflow-y-auto px-3 py-4">
          <slot />
        </div>
      </aside>
    </Transition>
  </Teleport>
</template>

<style scoped>
.backdrop-enter-active,
.backdrop-leave-active {
  transition: opacity 180ms ease;
}
.backdrop-enter-from,
.backdrop-leave-to {
  opacity: 0;
}

.drawer-enter-active,
.drawer-leave-active {
  transition: transform 220ms ease;
}
.drawer-enter-from,
.drawer-leave-to {
  transform: translateX(-100%);
}
</style>
