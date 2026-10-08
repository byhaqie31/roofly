<script setup lang="ts">
import { Headset } from "lucide-vue-next";
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import LegalSupportSection from "~/components/legal/LegalSupportSection.vue";

// Body of /owner/help and /tenant/help: a way into the Help & feedback widget
// (hidden in demo, like the widget itself) and the Legal section.
defineProps<{ audience: "owner" | "tenant" }>();

const { t } = useI18n();
const { showSupportWidget } = useEnv();
const supportWidget = useSupportWidget();
</script>

<template>
  <div class="max-w-form-readable">
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("help.title") }}</h1>
      <p class="mt-2 text-caption text-ink-muted">{{ t("help.subtitle") }}</p>
    </header>

    <div class="space-y-4 sm:space-y-6">
      <Card v-if="showSupportWidget" padding="loose">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div class="min-w-0">
            <h2 class="text-card-title font-semibold text-ink">{{ t("help.contactTitle") }}</h2>
            <p class="mt-1 text-caption text-ink-muted">{{ t("help.contactBody") }}</p>
          </div>
          <Button class="self-start sm:self-auto" @click="supportWidget.open()">
            <Headset :size="16" :stroke-width="1.75" />
            {{ t("help.contactCta") }}
          </Button>
        </div>
      </Card>

      <LegalSupportSection :audience="audience" />
    </div>
  </div>
</template>
