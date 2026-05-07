<script setup lang="ts">
import { computed } from "vue";

const props = withDefaults(
  defineProps<{
    modelValue: string | number | null;
    type?: string;
    label?: string;
    placeholder?: string;
    error?: string;
    autocomplete?: string;
    disabled?: boolean;
    size?: "md" | "lg";
    id?: string;
  }>(),
  { type: "text", size: "md", disabled: false },
);

defineEmits<{
  "update:modelValue": [value: string];
}>();

const inputId = computed(
  () => props.id ?? `input-${Math.random().toString(36).slice(2, 9)}`,
);
</script>

<template>
  <label :for="inputId" class="block">
    <span
      v-if="label"
      class="mb-1.5 block text-caption font-normal text-ink-strong"
    >
      {{ label }}
    </span>
    <input
      :id="inputId"
      :type="type"
      :value="modelValue"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      :disabled="disabled"
      :class="[
        'w-full rounded-sm border bg-surface-page px-3 transition outline-none',
        size === 'lg' ? 'h-12 text-body' : 'h-10 text-body',
        error
          ? 'border-accent shadow-focus'
          : 'border-line-passive focus:border-line-interactive focus:shadow-focus',
        'placeholder:text-ink-muted disabled:opacity-50',
      ]"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <span
      v-if="error"
      class="mt-1.5 block text-caption text-accent"
      role="alert"
    >
      {{ error }}
    </span>
  </label>
</template>
