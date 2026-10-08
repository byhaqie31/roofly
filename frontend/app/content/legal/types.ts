// Shape of a legal document's copy. One file per document per locale
// (privacy.en.ts, privacy.ms.ts, …), registered in ./index.ts and rendered by
// components/legal/LegalDocument.vue. Long-form copy lives here rather than in
// i18n JSON; short UI labels (links, headings of the chrome) stay in i18n.
//
// Inline markup: `[label](/legal/terms)` or `[label](mailto:…)` — nothing else.
// Never write contact details into the copy: use a `contact` block, which reads
// config/legal.ts and drops any value that's still null.
// Mark uncertain sentences with a `// TODO(legal)` comment beside them, never in
// the strings themselves (registry.test.ts fails on "TODO" in rendered copy).

export type LegalBlock =
  | { type: "p"; text: string }
  | { type: "ul"; items: string[] }
  /** Operator + contact details from config/legal.ts; `privacy` swaps in the privacy contact email. */
  | { type: "contact"; channel: "general" | "privacy" }
  /** Plan ladder from config/plans.ts. */
  | { type: "plans" };

export interface LegalSection {
  /** Anchor id — identical across locales. */
  id: string;
  heading: string;
  blocks: LegalBlock[];
}

export interface LegalDocumentContent {
  title: string;
  /** One or two plain sentences under the title. */
  summary: string;
  sections: LegalSection[];
}
