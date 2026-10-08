<script setup lang="ts">
import { computed } from "vue";
import { LEGAL } from "~/config/legal";
import { footerLinks, operatorLines, type FooterVariant } from "~/utils/legal";

/**
 * The one footer for every surface: legal links (config-driven), operator
 * details, copyright and the designer credit. Use it on any layout that needs
 * a footer — don't inline a footer block in a layout, extend this instead.
 *
 * variant (which links/lines show — decided in utils/legal.ts footerLinks()):
 *   full  — marketing, coming-soon and legal pages: every document, Contact,
 *           operator line with SSM number + contact details, credit
 *   slim  — auth and first-run screens: Privacy + Terms
 *   shell — owner + tenant app: Privacy + Terms + Help and support (`helpTo`)
 *   admin — admin back office: Privacy + Terms, admin accent, English only
 * The Beta terms link joins every non-admin variant when useEnv().showBetaTerms.
 *
 * tone (colour): `dark` / `light` keep the brand-orange palette for the
 * charcoal marketing + auth panes; `theme` uses the design tokens so it
 * follows light/dark mode inside the app shells and on the legal pages.
 */
type Tone = "dark" | "light" | "theme";

const props = withDefaults(
  defineProps<{
    tone?: Tone;
    variant?: FooterVariant;
    helpTo?: string;
  }>(),
  { tone: "dark", variant: "full", helpTo: undefined },
);

const { t } = useI18n();
const { showBetaTerms } = useEnv();
const year = new Date().getFullYear();

const links = computed(() =>
  footerLinks(props.variant, { showBetaTerms, helpTo: props.helpTo, contactEmail: LEGAL.contact.email }),
);
// Name first (rendered with its own sentence), then SSM / address / email / phone — nulls dropped.
const operatorExtras = computed(() => operatorLines(LEGAL).filter((l) => l.key !== "name"));

// Brand-orange on the charcoal/cream marketing panes; admin's own blue on its
// charcoal sign-in page. `theme` tone uses token classes instead (see below).
const palette = computed(() => {
  if (props.tone === "theme") return null;
  if (props.variant === "admin") return { border: "rgba(247, 244, 237, 0.12)", text: "rgba(247, 244, 237, 0.6)", link: "#7fa6c9" };
  return props.tone === "light"
    ? { border: "rgba(196, 77, 38, 0.18)", text: "rgba(196, 77, 38, 0.75)", link: "#c44d26" }
    : { border: "rgba(231, 106, 63, 0.18)", text: "rgba(231, 106, 63, 0.75)", link: "#e76a3f" };
});

// The full footer draws its divider between the legal links and the bottom
// line (edge to edge), so the outer border + padding only apply to the slim ones.
const footerClass = computed(() => [
  "relative z-10",
  props.variant === "full" ? "mt-16 pt-8" : "border-t px-4 py-4 sm:px-6 lg:px-12",
  palette.value ? "" : "border-line-passive text-ink-muted",
]);
const dividerStyle = computed(() => (palette.value ? { borderColor: palette.value.border } : undefined));

const linkClass = computed(() => [
  "rounded-sm underline-offset-2 hover:underline focus-visible:shadow-focus transition-colors",
  palette.value ? "" : props.variant === "admin" ? "text-ink-body hover:text-admin" : "text-ink-body hover:text-ink",
]);
const linkStyle = computed(() => (palette.value ? { color: palette.value.link } : undefined));
</script>

<template>
  <footer
    :class="footerClass"
    :style="palette ? { borderColor: palette.border, color: palette.text } : undefined"
  >
    <!-- Legal links (+ © on the slim variants). flex-wrap + gap, no "·"
         separators, so longer BM labels break cleanly on narrow screens. -->
    <nav
      :aria-label="t('legal.footerNav')"
      class="mx-auto max-w-app text-center text-micro"
      :class="variant === 'full' ? 'px-4 pb-6 sm:px-6 lg:px-12' : ''"
    >
      <ul class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5">
        <li v-if="variant !== 'full'">© {{ year }} Roofly.my</li>
        <li v-for="link in links" :key="link.key">
          <NuxtLink :to="link.to" :external="link.external" :class="linkClass" :style="linkStyle">
            {{ t(link.labelKey) }}
          </NuxtLink>
        </li>
      </ul>
    </nav>

    <!-- Full variant: the footer line, then one full-width row — © and the
         operator identity on the left (Consumer Protection (E-Trade)
         Regulations 2024 disclosures; every value from config/legal.ts, nulls
         never render), designer credit on the right. Stacks and centres below md. -->
    <div
      v-if="variant === 'full'"
      class="flex flex-col items-center gap-1.5 border-t px-4 py-5 text-center text-micro sm:px-6 md:flex-row md:items-start md:justify-between md:gap-6 md:text-left lg:px-12"
      :class="palette ? '' : 'border-line-passive'"
      :style="dividerStyle"
    >
      <ul class="flex flex-wrap items-center justify-center gap-x-1.5 gap-y-1 md:justify-start">
        <li>© {{ year }} Roofly.my</li>
        <li>
          <span aria-hidden="true" class="mr-1.5">·</span>
          <i18n-t keypath="legal.footerProductOf" tag="span" scope="global">
            <template #name>
              <a
                v-if="LEGAL.operator.website"
                :href="LEGAL.operator.website"
                target="_blank"
                rel="noopener noreferrer"
                class="font-medium"
                :class="linkClass"
                :style="linkStyle"
              >{{ LEGAL.operator.name }}</a>
              <span v-else>{{ LEGAL.operator.name }}</span>
            </template>
          </i18n-t>
        </li>
        <li v-for="line in operatorExtras" :key="line.key" class="tabular-nums">
          <span aria-hidden="true" class="mr-1.5">·</span>
          <template v-if="line.key === 'ssmNumber'">{{ t("legal.operator.ssmNumber", { value: line.value }) }}</template>
          <a v-else-if="line.href" :href="line.href" :class="linkClass" :style="linkStyle">{{ line.value }}</a>
          <template v-else>{{ line.value }}</template>
        </li>
      </ul>
      <p class="shrink-0 md:text-right">
        Designed and developed with care and love by
        <a
          href="https://baihaqie.com"
          target="_blank"
          rel="noopener noreferrer"
          class="font-medium underline underline-offset-2 hover:opacity-80 transition-opacity"
          :class="palette ? '' : 'text-ink-body'"
          :style="linkStyle"
        >Qie</a>
      </p>
    </div>
  </footer>
</template>
