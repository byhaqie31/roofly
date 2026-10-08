// Registry: legal slug → locale → document copy. The only way pages and tests
// reach the content files. Adding a document = add the slug to config/legal.ts
// + LEGAL_SLUGS in utils/legal.ts, write both locale files, register them here.

import type { LegalSlug } from "~/config/legal";
import type { LegalDocumentContent } from "./types";
import privacyEn from "./privacy.en";
import privacyMs from "./privacy.ms";
import termsEn from "./terms.en";
import termsMs from "./terms.ms";
import billingEn from "./billing.en";
import billingMs from "./billing.ms";
import acceptableUseEn from "./acceptable-use.en";
import acceptableUseMs from "./acceptable-use.ms";
import betaEn from "./beta.en";
import betaMs from "./beta.ms";

export type LegalLocale = "en" | "ms";

export const LEGAL_LOCALES: readonly LegalLocale[] = ["en", "ms"];

export const LEGAL_DOCUMENTS: Record<LegalSlug, Record<LegalLocale, LegalDocumentContent>> = {
  privacy: { en: privacyEn, ms: privacyMs },
  terms: { en: termsEn, ms: termsMs },
  billing: { en: billingEn, ms: billingMs },
  "acceptable-use": { en: acceptableUseEn, ms: acceptableUseMs },
  beta: { en: betaEn, ms: betaMs },
};

/** Unknown locales fall back to English (the i18n default). */
export const getLegalDocument = (slug: LegalSlug, locale: string): LegalDocumentContent =>
  LEGAL_DOCUMENTS[slug][locale === "ms" ? "ms" : "en"];

export type { LegalBlock, LegalSection, LegalDocumentContent } from "./types";
