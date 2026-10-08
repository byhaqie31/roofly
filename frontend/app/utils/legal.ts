// Pure helpers behind the legal pages, footers and help pages. No Nuxt imports,
// so they're Vitest-covered (utils/legal.test.ts). Components translate the
// returned `labelKey`s and render; the decisions about *what* shows live here.

import type { LegalConfig, LegalSlug } from "~/config/legal";

/** Display order everywhere a full list of documents is shown. */
export const LEGAL_SLUGS: readonly LegalSlug[] = ["privacy", "terms", "billing", "acceptable-use", "beta"];

export const legalPath = (slug: LegalSlug) => `/legal/${slug}`;

export const isLegalPath = (path: string) => path === "/legal" || path.startsWith("/legal/");

/** The beta terms only exist where beta applies (useEnv().showBetaTerms). */
export const isLegalSlugAvailable = (slug: string, opts: { showBetaTerms: boolean }): slug is LegalSlug =>
  (LEGAL_SLUGS as readonly string[]).includes(slug) && (slug !== "beta" || opts.showBetaTerms);

export const legalLabelKey = (slug: LegalSlug) => `legal.docs.${slug}`;

/** Every document a reader can open here, in display order. */
export const availableLegalSlugs = (opts: { showBetaTerms: boolean }) =>
  LEGAL_SLUGS.filter((s) => isLegalSlugAvailable(s, opts));

// ── Footers ────────────────────────────────────────────────────────────────

/**
 * full  — marketing, coming-soon and legal pages (contact details sit beside
 *         the links, so there's no separate Contact link)
 * slim  — auth pages and other first-run screens
 * shell — owner + tenant app shells (adds the help page)
 * admin — admin back office (English only, no beta terms: staff aren't testers)
 */
export type FooterVariant = "full" | "slim" | "shell" | "admin";

export interface FooterLink {
  key: string;
  labelKey: string;
  to: string;
  /** mailto: and other non-router links render as <a>. */
  external: boolean;
}

const docLink = (slug: LegalSlug): FooterLink => ({ key: slug, labelKey: legalLabelKey(slug), to: legalPath(slug), external: false });

export const footerLinks = (
  variant: FooterVariant,
  opts: { showBetaTerms: boolean; helpTo?: string | null },
): FooterLink[] => {
  const slugs: LegalSlug[] = variant === "full" ? ["privacy", "terms", "billing", "acceptable-use"] : ["privacy", "terms"];
  const links = slugs.map(docLink);
  if (opts.showBetaTerms && variant !== "admin") links.push(docLink("beta"));
  if (variant === "shell" && opts.helpTo) {
    links.push({ key: "help", labelKey: "legal.links.help", to: opts.helpTo, external: false });
  }
  return links;
};

// ── Operator details ───────────────────────────────────────────────────────

export type OperatorLineKey = "name" | "ssmNumber" | "registeredAddress" | "email" | "privacyEmail" | "phone";

export interface OperatorLine {
  key: OperatorLineKey;
  value: string;
  /** mailto:/tel: target when the line is a contact method. */
  href?: string;
}

/**
 * Operator + contact lines in display order, with every `null` field dropped.
 * `channel: "privacy"` shows the privacy contact email instead of the general
 * one (falling back to the general email while the privacy one is unset).
 */
export const operatorLines = (
  config: LegalConfig,
  opts: { includeContact?: boolean; channel?: "general" | "privacy" } = {},
): OperatorLine[] => {
  const { includeContact = true, channel = "general" } = opts;
  const lines: Array<OperatorLine | null> = [
    { key: "name", value: config.operator.name },
    config.operator.ssmNumber ? { key: "ssmNumber", value: config.operator.ssmNumber } : null,
    config.operator.registeredAddress ? { key: "registeredAddress", value: config.operator.registeredAddress } : null,
  ];
  if (includeContact) {
    const privacy = channel === "privacy" ? config.contact.privacyEmail : null;
    const email = privacy ?? config.contact.email;
    lines.push(
      email ? { key: privacy ? "privacyEmail" : "email", value: email, href: `mailto:${email}` } : null,
      config.contact.phone ? { key: "phone", value: config.contact.phone, href: `tel:${config.contact.phone.replace(/[^\d+]/g, "")}` } : null,
    );
  }
  return lines.filter((l): l is OperatorLine => l !== null && l.value.trim() !== "");
};

// ── Inline copy ────────────────────────────────────────────────────────────

export type InlineSegment = { text: string; href?: undefined } | { text: string; href: string };

/**
 * Splits document copy on `[label](href)` links — the only inline markup the
 * legal content files use. Everything else is plain text.
 */
export const inlineSegments = (text: string): InlineSegment[] => {
  const out: InlineSegment[] = [];
  const re = /\[([^\]]+)\]\(([^)\s]+)\)/g;
  let last = 0;
  let m: RegExpExecArray | null;
  while ((m = re.exec(text)) !== null) {
    if (m.index > last) out.push({ text: text.slice(last, m.index) });
    out.push({ text: m[1]!, href: m[2]! });
    last = m.index + m[0].length;
  }
  if (last < text.length) out.push({ text: text.slice(last) });
  return out;
};

/** ISO date → "7 October 2026" / "7 Oktober 2026". Null in, null out. */
export const formatLegalDate = (iso: string | null, locale: string): string | null => {
  if (!iso) return null;
  const d = new Date(`${iso}T00:00:00Z`);
  if (Number.isNaN(d.getTime())) return null;
  return new Intl.DateTimeFormat(locale === "ms" ? "ms-MY" : "en-MY", {
    day: "numeric",
    month: "long",
    year: "numeric",
    timeZone: "UTC",
  }).format(d);
};
