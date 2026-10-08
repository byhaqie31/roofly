<script setup lang="ts">
import { computed, ref } from "vue";
import { Mail, MapPin, Phone } from "lucide-vue-next";
import { LEGAL } from "~/config/legal";
import ContactUsModal from "~/components/legal/ContactUsModal.vue";
import { contactOptions, footerLinks, operatorLines, type FooterVariant } from "~/utils/legal";

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
    /** Where legal links point — the in-app copies ("/owner/legal") in the shells; public /legal elsewhere. */
    legalBase?: string;
  }>(),
  { tone: "dark", variant: "full", helpTo: undefined, legalBase: undefined },
);

const { t } = useI18n();
const { showBetaTerms, showEnvBanner } = useEnv();
const year = new Date().getFullYear();

// Slim footer's "Contact us" pop-up (email / call / WhatsApp) — hidden when no
// contact details are configured.
const contactOpen = ref(false);
const hasContact = contactOptions(LEGAL).length > 0;

const links = computed(() =>
  footerLinks(props.variant, { showBetaTerms, helpTo: props.helpTo, legalBase: props.legalBase }),
);
// Full footer: address / email / phone as icon links on the right of the legal
// links (nulls dropped). The value is the accessible name + hover title. The
// SSM number lives on the legal pages' operator block, not here.
const contactIcons = { registeredAddress: MapPin, email: Mail, phone: Phone } as const;
const contactLinks = computed(() =>
  operatorLines(LEGAL)
    .filter((l): l is typeof l & { key: keyof typeof contactIcons } => l.key in contactIcons)
    .map((l) => ({
      key: l.key,
      value: l.value,
      icon: contactIcons[l.key],
      href: l.href ?? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(l.value)}`,
      external: !l.href,
    })),
);

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
  // Slim: © sits at the left edge, clear of UAT's fixed EnvBanner toggle.
  props.variant === "slim" && showEnvBanner ? "md:pl-16 lg:pl-16" : "",
  palette.value ? "" : "border-line-passive text-ink-muted",
]);
// UAT's fixed bottom-left EnvBanner toggle would cover the start of the © line,
// so both full-footer rows share this indent from md: up (no extra height).
const rowIndent = computed(() => (showEnvBanner ? "md:pl-16 lg:pl-16" : ""));
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
    <!-- Full variant, row 1: the legal links, above the footer line. Same left
         edge as row 2 (including its UAT indent) so both rows start together. -->
    <div
      v-if="variant === 'full'"
      class="flex flex-col items-center gap-3 px-4 pb-6 text-center text-micro sm:px-6 md:flex-row md:items-start md:justify-between md:gap-8 md:text-left lg:px-12"
      :class="rowIndent"
    >
      <nav :aria-label="t('legal.footerNav')">
        <ul class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 md:justify-start">
          <li v-for="link in links" :key="link.key">
            <NuxtLink :to="link.to" :external="link.external" :class="linkClass" :style="linkStyle">
              {{ t(link.labelKey) }}
            </NuxtLink>
          </li>
        </ul>
      </nav>
    </div>

    <!-- Slim (auth, onboarding, suspended): © on the left, legal links at the
         right edge from md:; below md the links sit on top and © underneath
         (same order as the full footer). -->
    <div
      v-else-if="variant === 'slim'"
      class="flex flex-col-reverse items-center gap-1.5 text-center text-micro md:flex-row md:justify-between md:gap-6 md:text-left"
    >
      <p>© {{ year }} Roofly.my</p>
      <nav :aria-label="t('legal.footerNav')">
        <ul class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 md:justify-end">
          <li v-for="link in links" :key="link.key">
            <NuxtLink :to="link.to" :external="link.external" :class="linkClass" :style="linkStyle">
              {{ t(link.labelKey) }}
            </NuxtLink>
          </li>
          <li v-if="hasContact">
            <button type="button" :class="linkClass" :style="linkStyle" @click="contactOpen = true">
              {{ t("legal.contactUs.link") }}
            </button>
          </li>
        </ul>
      </nav>
      <ContactUsModal v-if="hasContact" v-model:open="contactOpen" />
    </div>

    <!-- Shell + admin: © and the legal links on one centred line. flex-wrap +
         gap, no "·" separators, so longer BM labels break cleanly. -->
    <nav v-else :aria-label="t('legal.footerNav')" class="mx-auto max-w-app text-center text-micro">
      <ul class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5">
        <li>© {{ year }} Roofly.my</li>
        <li v-for="link in links" :key="link.key">
          <NuxtLink :to="link.to" :external="link.external" :class="linkClass" :style="linkStyle">
            {{ t(link.labelKey) }}
          </NuxtLink>
        </li>
      </ul>
    </nav>

    <!-- Full variant, row 2: the footer line, then © · operator on the left and
         the designer credit | contact icons on the right. On UAT the fixed bottom-left EnvBanner
         toggle would cover the ©, so the row is indented past it from md: up
         (no extra height). -->
    <div
      v-if="variant === 'full'"
      class="flex flex-col items-center gap-1.5 border-t px-4 py-5 text-center text-micro sm:px-6 md:flex-row md:items-start md:justify-between md:gap-6 md:text-left lg:px-12"
      :class="[palette ? '' : 'border-line-passive', rowIndent]"
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
      </ul>
      <div class="flex shrink-0 flex-wrap items-center justify-center gap-x-3 gap-y-1.5 md:justify-end">
        <p>
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
        <!-- Operator contact (Consumer Protection (E-Trade) Regulations 2024
             disclosures) as icon links — every value from config/legal.ts,
             nulls never render. The full values are also on every legal page. -->
        <template v-if="contactLinks.length">
          <span aria-hidden="true" class="h-3 w-px bg-current opacity-40" />
          <address class="not-italic">
            <ul class="flex items-center gap-3">
              <li v-for="c in contactLinks" :key="c.key">
                <a
                  :href="c.href"
                  :target="c.external ? '_blank' : undefined"
                  :rel="c.external ? 'noopener noreferrer' : undefined"
                  :aria-label="c.value"
                  :title="c.value"
                  class="inline-flex rounded-sm hover:opacity-80 focus-visible:shadow-focus transition-opacity"
                  :class="palette ? '' : 'text-ink-body hover:text-ink'"
                  :style="linkStyle"
                >
                  <component :is="c.icon" :size="16" :stroke-width="1.75" aria-hidden="true" />
                </a>
              </li>
            </ul>
          </address>
        </template>
      </div>
    </div>
  </footer>
</template>
