<script setup lang="ts">
import type * as lucide from "lucide-vue-next";
import EmptyState from "~/components/ui/EmptyState.vue";
import Icon from "~/components/ui/Icon.vue";

const { t } = useI18n();

interface DocSlot {
  key: string;
  icon: keyof typeof lucide;
}

const slots: DocSlot[] = [
  { key: "lease", icon: "FileCheck" },
  { key: "addendum", icon: "FilePlus" },
  { key: "inspection", icon: "ClipboardCheck" },
  { key: "inventory", icon: "List" },
  { key: "exit", icon: "FileX" },
];
</script>

<template>
  <div class="space-y-5">
    <p class="text-caption text-ink-muted">
      {{ t("owner.agreements.detail.documents.help") }}
    </p>

    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
      <li
        v-for="slot in slots"
        :key="slot.key"
        class="flex items-start gap-3 rounded-md border border-line-passive bg-surface-page p-4"
      >
        <span
          class="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-surface-raised text-ink-muted"
        >
          <Icon :name="slot.icon" :size="18" />
        </span>
        <div class="min-w-0 flex-1">
          <div class="text-body font-medium text-ink">
            {{ t(`owner.agreements.detail.documents.slots.${slot.key}.title`) }}
          </div>
          <p class="mt-0.5 text-caption text-ink-muted">
            {{ t(`owner.agreements.detail.documents.slots.${slot.key}.help`) }}
          </p>
        </div>
      </li>
    </ul>

    <div
      class="rounded-md border border-dashed border-line-passive bg-surface-page p-6"
    >
      <EmptyState
        icon="CloudUpload"
        :title="t('owner.agreements.detail.documents.placeholderTitle')"
        :description="t('owner.agreements.detail.documents.placeholderHelp')"
      />
    </div>
  </div>
</template>
