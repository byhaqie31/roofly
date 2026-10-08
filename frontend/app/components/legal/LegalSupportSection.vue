<script setup lang="ts">
import { computed } from "vue";
import { ChevronRight } from "lucide-vue-next";
import Card from "~/components/ui/Card.vue";
import LegalContactDetails from "~/components/legal/LegalContactDetails.vue";
import { LEGAL } from "~/config/legal";
import { availableLegalSlugs, formatLegalDate, legalLabelKey, legalPath } from "~/utils/legal";

// "Legal" section on the owner + tenant Help and support pages: every legal
// document with its effective date, the operator details, and how to ask for
// access to / correction of personal data. All values from config/legal.ts.
const props = defineProps<{ audience: "owner" | "tenant" }>();

const { t, locale } = useI18n();
const { showBetaTerms, showSupportWidget } = useEnv();
const privacyEmail = LEGAL.contact.privacyEmail;

const docs = computed(() =>
  availableLegalSlugs({ showBetaTerms }).map((slug) => ({
    slug,
    to: legalPath(slug),
    label: t(legalLabelKey(slug)),
    effective: formatLegalDate(LEGAL.documents[slug].effectiveDate, locale.value),
  })),
);
</script>

<template>
  <Card padding="loose">
    <h2 class="text-card-title font-semibold text-ink">{{ t("legal.support.title") }}</h2>
    <p class="mt-1 text-caption text-ink-muted">{{ t("legal.support.description") }}</p>

    <ul class="mt-5 divide-y divide-line-passive border-y border-line-passive">
      <li v-for="doc in docs" :key="doc.slug">
        <NuxtLink
          :to="doc.to"
          class="flex items-center justify-between gap-3 px-1 py-3 hover:bg-surface-hover focus-visible:shadow-focus transition-colors"
        >
          <span class="min-w-0">
            <span class="block text-body text-ink">{{ doc.label }}</span>
            <span v-if="doc.effective" class="block text-caption text-ink-muted tabular-nums">
              {{ t("legal.effective", { date: doc.effective }) }}
            </span>
          </span>
          <ChevronRight :size="18" :stroke-width="1.5" class="shrink-0 text-ink-faint" />
        </NuxtLink>
      </li>
    </ul>

    <div class="mt-8 grid gap-8 md:grid-cols-2">
      <section>
        <h3 class="text-body font-semibold text-ink">{{ t("legal.support.yourDataTitle") }}</h3>
        <div class="mt-2 space-y-2 text-caption text-ink-body">
          <p>{{ t("legal.support.yourDataBody") }}</p>
          <p v-if="privacyEmail">
            {{ t("legal.support.yourDataPrivacyEmail") }}
            <a :href="`mailto:${privacyEmail}`" class="text-ink underline underline-offset-2 hover:text-accent">{{ privacyEmail }}</a>
          </p>
          <p v-if="showSupportWidget">{{ t("legal.support.yourDataWidget") }}</p>
          <p v-if="props.audience === 'tenant'">{{ t("legal.support.yourDataTenant") }}</p>
        </div>
      </section>
      <section>
        <h3 class="mb-2 text-body font-semibold text-ink">{{ t("legal.operator.heading") }}</h3>
        <LegalContactDetails />
      </section>
    </div>
  </Card>
</template>
