<script setup lang="ts">
import { computed, ref, watch } from "vue";
import Select from "~/components/ui/Select.vue";
import LegalInline from "~/components/legal/LegalInline.vue";
import LegalContactDetails from "~/components/legal/LegalContactDetails.vue";
import LegalPlansTable from "~/components/legal/LegalPlansTable.vue";
import { LEGAL, type LegalSlug } from "~/config/legal";
import { getLegalDocument } from "~/content/legal";
import { availableLegalSlugs, formatLegalDate, legalLabelKey, legalPath } from "~/utils/legal";

// Shared reading layout for every legal document (UI-STANDARDS § 11.22):
// narrow column, title + effective date + version, a section list (sticky
// aside on lg, inline list on sm–lg, dropdown under sm), operator details.
// Static copy — no motion, no data fetching.
const props = defineProps<{ slug: LegalSlug }>();

const { t, locale, setLocale } = useI18n();
const { showBetaTerms } = useEnv();

const doc = computed(() => getLegalDocument(props.slug, locale.value));
const meta = computed(() => LEGAL.documents[props.slug]);
const effective = computed(() => formatLegalDate(meta.value.effectiveDate, locale.value));

const sectionOptions = computed(() => doc.value.sections.map((s) => ({ value: s.id, label: s.heading })));
const otherDocs = computed(() => availableLegalSlugs({ showBetaTerms }).filter((s) => s !== props.slug));

// Mobile section picker: jump straight to the section (no smooth scroll).
const jumpTo = ref<string | null>(null);
watch(jumpTo, (id) => {
  if (!id || typeof document === "undefined") return;
  document.getElementById(id)?.scrollIntoView({ block: "start" });
  history.replaceState(history.state, "", `#${id}`);
});

const toggleLocale = () => setLocale(locale.value === "ms" ? "en" : "ms");
</script>

<template>
  <div class="mx-auto w-full max-w-[1000px] px-4 py-10 sm:px-6 sm:py-16 lg:grid lg:grid-cols-[200px_minmax(0,680px)] lg:justify-center lg:gap-16">
    <!-- lg+: sticky section list beside the text -->
    <aside class="hidden lg:block">
      <nav :aria-label="t('legal.onThisPage')" class="sticky top-8">
        <p class="mb-3 text-caption text-ink-muted">{{ t("legal.onThisPage") }}</p>
        <ol class="space-y-2 border-l border-line-passive">
          <li v-for="s in doc.sections" :key="s.id">
            <a
              :href="`#${s.id}`"
              class="-ml-px block border-l border-transparent pl-3 text-caption text-ink-muted hover:border-line-interactive hover:text-ink focus-visible:shadow-focus"
            >{{ s.heading }}</a>
          </li>
        </ol>
      </nav>
    </aside>

    <article class="mx-auto min-w-0 max-w-[680px] lg:mx-0">
      <header class="mb-8">
        <p class="text-caption text-ink-muted">{{ t("legal.eyebrow") }}</p>
        <h1 class="mt-2 text-[1.875rem] font-semibold leading-[1.15] text-ink sm:text-display-sub">{{ doc.title }}</h1>
        <p class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-ink-muted tabular-nums">
          <span v-if="effective">{{ t("legal.effective", { date: effective }) }}</span>
          <span v-if="effective" aria-hidden="true">·</span>
          <span>{{ t("legal.version", { version: meta.version }) }}</span>
          <span aria-hidden="true">·</span>
          <button
            type="button"
            :lang="locale === 'ms' ? 'en' : 'ms'"
            class="rounded-sm text-ink underline underline-offset-2 hover:text-accent focus-visible:shadow-focus"
            @click="toggleLocale"
          >{{ t("legal.readIn") }}</button>
        </p>
        <p class="mt-5 text-body-lg text-ink-body"><LegalInline :text="doc.summary" /></p>
      </header>

      <!-- <sm: section dropdown (UI-STANDARDS § 11.1 pattern) -->
      <div class="mb-8 sm:hidden">
        <Select v-model="jumpTo" :options="sectionOptions" :label="t('legal.onThisPage')" :placeholder="t('legal.jumpTo')" />
      </div>

      <!-- sm–lg: inline section list -->
      <nav :aria-label="t('legal.onThisPage')" class="mb-10 hidden rounded-lg border border-line-passive p-5 sm:block lg:hidden">
        <p class="mb-3 text-caption text-ink-muted">{{ t("legal.onThisPage") }}</p>
        <ol class="grid grid-cols-2 gap-x-6 gap-y-1.5">
          <li v-for="s in doc.sections" :key="s.id">
            <a :href="`#${s.id}`" class="text-caption text-ink-body underline-offset-2 hover:text-ink hover:underline focus-visible:shadow-focus">{{ s.heading }}</a>
          </li>
        </ol>
      </nav>

      <section v-for="s in doc.sections" :id="s.id" :key="s.id" class="mt-10 scroll-mt-6 first-of-type:mt-0">
        <h2 class="text-card-title font-semibold text-ink">{{ s.heading }}</h2>
        <div class="mt-3 space-y-4 text-body leading-relaxed text-ink-body">
          <template v-for="(block, i) in s.blocks" :key="i">
            <p v-if="block.type === 'p'"><LegalInline :text="block.text" /></p>
            <ul v-else-if="block.type === 'ul'" class="list-disc space-y-2 pl-5 marker:text-ink-faint">
              <li v-for="(item, j) in block.items" :key="j"><LegalInline :text="item" /></li>
            </ul>
            <LegalContactDetails v-else-if="block.type === 'contact'" :channel="block.channel" />
            <LegalPlansTable v-else-if="block.type === 'plans'" />
          </template>
        </div>
      </section>

      <footer class="mt-14 space-y-8 border-t border-line-passive pt-8">
        <div>
          <h2 class="mb-3 text-caption text-ink-muted">{{ t("legal.operator.heading") }}</h2>
          <LegalContactDetails />
        </div>
        <nav v-if="otherDocs.length" :aria-label="t('legal.otherDocs')">
          <h2 class="mb-3 text-caption text-ink-muted">{{ t("legal.otherDocs") }}</h2>
          <ul class="flex flex-wrap gap-x-5 gap-y-2 text-caption">
            <li v-for="s in otherDocs" :key="s">
              <NuxtLink :to="legalPath(s)" class="text-ink underline underline-offset-2 hover:text-accent focus-visible:shadow-focus">
                {{ t(legalLabelKey(s)) }}
              </NuxtLink>
            </li>
          </ul>
        </nav>
      </footer>
    </article>
  </div>
</template>
