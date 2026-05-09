<script setup lang="ts">
import { computed } from "vue";
import {
  SelectRoot,
  SelectTrigger,
  SelectValue,
  SelectIcon,
  SelectPortal,
  SelectContent,
  SelectViewport,
  SelectItem,
  SelectItemText,
  SelectItemIndicator,
} from "reka-ui";
import Icon from "~/components/ui/Icon.vue";

interface Option {
  value: string;
  label: string;
  disabled?: boolean;
}

const props = withDefaults(
  defineProps<{
    modelValue: string | null | undefined;
    options: Option[];
    label?: string;
    placeholder?: string;
    error?: string;
    disabled?: boolean;
    id?: string;
  }>(),
  { disabled: false },
);

defineEmits<{
  "update:modelValue": [value: string];
}>();

const fieldId = computed(
  () => props.id ?? `select-${Math.random().toString(36).slice(2, 9)}`,
);
</script>

<template>
  <div>
    <label
      v-if="label"
      :for="fieldId"
      class="mb-1.5 block text-caption font-normal text-ink-strong"
    >
      {{ label }}
    </label>
    <SelectRoot
      :model-value="modelValue ?? undefined"
      :disabled="disabled"
      @update:model-value="$emit('update:modelValue', $event as string)"
    >
      <SelectTrigger
        :id="fieldId"
        :class="[
          'inline-flex h-10 w-full items-center justify-between gap-2 rounded-sm border bg-surface-page px-3 text-body outline-none transition',
          error
            ? 'border-accent shadow-focus'
            : 'border-line-passive focus:border-line-interactive focus:shadow-focus data-[state=open]:border-line-interactive data-[state=open]:shadow-focus',
          'disabled:opacity-50',
        ]"
      >
        <SelectValue
          :placeholder="placeholder ?? ''"
          class="text-left text-ink data-[placeholder]:text-ink-muted"
        />
        <SelectIcon class="text-ink-muted">
          <Icon name="ChevronDown" :size="16" />
        </SelectIcon>
      </SelectTrigger>

      <SelectPortal>
        <SelectContent
          position="popper"
          :side-offset="4"
          class="z-50 min-w-[var(--reka-select-trigger-width)] max-h-[20rem] overflow-hidden rounded-md border border-line-passive bg-surface-raised shadow-modal"
        >
          <SelectViewport class="p-1">
            <SelectItem
              v-for="opt in options"
              :key="opt.value"
              :value="opt.value"
              :disabled="opt.disabled"
              class="relative flex cursor-pointer select-none items-center rounded-xs px-3 py-2 pr-8 text-body text-ink outline-none data-[highlighted]:bg-[rgba(28,28,28,0.04)] data-[disabled]:pointer-events-none data-[disabled]:opacity-40 data-[state=checked]:font-medium"
            >
              <SelectItemText>{{ opt.label }}</SelectItemText>
              <SelectItemIndicator
                class="absolute right-2 inline-flex items-center text-ink-muted"
              >
                <Icon name="Check" :size="14" />
              </SelectItemIndicator>
            </SelectItem>
          </SelectViewport>
        </SelectContent>
      </SelectPortal>
    </SelectRoot>
    <span
      v-if="error"
      class="mt-1.5 block text-caption text-accent"
      role="alert"
    >
      {{ error }}
    </span>
  </div>
</template>
