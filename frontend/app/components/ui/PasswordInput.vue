<script setup lang="ts">
import { ref } from "vue";
import { Eye, EyeOff } from "lucide-vue-next";
import Input from "~/components/ui/Input.vue";

/** `Input` with a show/hide toggle. The toggle is a real button, so it never steals the label's focus. */
defineProps<{
  modelValue: string;
  label?: string;
  placeholder?: string;
  error?: string;
  autocomplete?: string;
  disabled?: boolean;
  size?: "md" | "lg";
  id?: string;
}>();

const emit = defineEmits<{ "update:modelValue": [value: string] }>();

const { t } = useI18n();
const visible = ref(false);
</script>

<template>
  <Input
    :model-value="modelValue"
    :type="visible ? 'text' : 'password'"
    :label="label"
    :placeholder="placeholder"
    :error="error"
    :autocomplete="autocomplete"
    :disabled="disabled"
    :size="size"
    :id="id"
    @update:model-value="emit('update:modelValue', String($event ?? ''))"
  >
    <template #suffix>
      <button
        type="button"
        class="pointer-events-auto inline-flex h-8 w-8 items-center justify-center rounded-sm text-ink-muted transition hover:bg-surface-hover hover:text-ink focus-visible:shadow-focus focus-visible:outline-none"
        :aria-label="visible ? t('auth.hidePassword') : t('auth.showPassword')"
        :aria-pressed="visible"
        :disabled="disabled"
        @click="visible = !visible"
      >
        <EyeOff v-if="visible" :size="18" :stroke-width="1.5" />
        <Eye v-else :size="18" :stroke-width="1.5" />
      </button>
    </template>
  </Input>
</template>
