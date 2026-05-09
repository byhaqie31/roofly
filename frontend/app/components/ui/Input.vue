<script setup lang="ts">
import { computed, useSlots } from "vue";

const props = withDefaults(
  defineProps<{
    modelValue: string | number | null | undefined;
    type?: string;
    label?: string;
    placeholder?: string;
    error?: string;
    autocomplete?: string;
    disabled?: boolean;
    size?: "md" | "lg";
    id?: string;
    step?: string | number;
    min?: string | number;
    max?: string | number;
  }>(),
  { type: "text", size: "md", disabled: false },
);

const slots = useSlots();
const hasSuffix = computed(() => Boolean(slots.suffix));

const emit = defineEmits<{
  "update:modelValue": [value: string | number | undefined];
}>();

const onInput = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (props.type === "number") {
    if (target.value === "") {
      emit("update:modelValue", undefined);
    } else {
      const n = target.valueAsNumber;
      emit("update:modelValue", Number.isNaN(n) ? undefined : n);
    }
  } else {
    emit("update:modelValue", target.value);
  }
};

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
    <div class="relative">
      <input
        :id="inputId"
        :type="type"
        :value="modelValue ?? ''"
        :placeholder="placeholder"
        :autocomplete="autocomplete"
        :disabled="disabled"
        :step="step"
        :min="min"
        :max="max"
        :class="[
          'w-full rounded-sm border bg-surface-page transition outline-none',
          size === 'lg' ? 'h-12 text-body' : 'h-10 text-body',
          hasSuffix ? 'pl-3 pr-24' : 'px-3',
          error
            ? 'border-accent shadow-focus'
            : 'border-line-passive focus:border-line-interactive focus:shadow-focus',
          'placeholder:text-ink-muted disabled:opacity-50',
        ]"
        @input="onInput"
      />
      <span
        v-if="hasSuffix"
        class="pointer-events-none absolute inset-y-0 right-2 flex items-center"
      >
        <slot name="suffix" />
      </span>
    </div>
    <span
      v-if="error"
      class="mt-1.5 block text-caption text-accent"
      role="alert"
    >
      {{ error }}
    </span>
  </label>
</template>
