<script setup lang="ts">
import { computed } from "vue";
import { LEGAL } from "~/config/legal";
import { operatorLines, type OperatorLine } from "~/utils/legal";

// Operator + contact details from config/legal.ts. Null values are dropped by
// operatorLines(), so an unset SSM number or email simply doesn't render.
const props = withDefaults(defineProps<{ channel?: "general" | "privacy" }>(), { channel: "general" });

const { t } = useI18n();
const lines = computed(() => operatorLines(LEGAL, { channel: props.channel }));

const label = (line: OperatorLine) => {
  switch (line.key) {
    case "email":
      return t("legal.operator.email");
    case "privacyEmail":
      return t("legal.operator.privacyEmail");
    case "phone":
      return t("legal.operator.phone");
    default:
      return null;
  }
};
</script>

<template>
  <address class="not-italic rounded-md border border-line-passive bg-surface-raised px-4 py-3 text-caption text-ink-body">
    <ul class="space-y-1">
      <li v-for="line in lines" :key="line.key">
        <template v-if="line.key === 'name'">{{ t("legal.operator.productOf", { name: line.value }) }}</template>
        <template v-else-if="line.key === 'ssmNumber'">
          <span class="tabular-nums">{{ t("legal.operator.ssmNumber", { value: line.value }) }}</span>
        </template>
        <template v-else-if="line.href">
          <span class="text-ink-muted">{{ label(line) }}:</span>
          {{ " " }}
          <a :href="line.href" class="text-ink underline underline-offset-2 hover:text-accent tabular-nums">{{ line.value }}</a>
        </template>
        <template v-else>{{ line.value }}</template>
      </li>
    </ul>
  </address>
</template>
