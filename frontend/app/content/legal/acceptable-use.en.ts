import type { LegalDocumentContent } from "./types";

// Acceptable use policy. DRAFT for legal review.
const doc: LegalDocumentContent = {
  title: "Acceptable use",
  summary:
    "These rules keep Roofly safe and fair for owners and tenants. They are part of our [terms of service](/legal/terms).",
  sections: [
    {
      id: "do-not",
      heading: "What you must not do",
      blocks: [
        {
          type: "ul",
          items: [
            "Create fake owners, tenants, properties, agreements, invoices or payment records, or use Roofly to mislead anyone.",
            "Harass, threaten, abuse or discriminate against anyone, including through maintenance issues, comments or support messages.",
            "Enter, upload or share anyone else's personal data unless you have the right to, or use someone's data for anything other than managing the tenancy it belongs to.",
            "Sign in as someone else, share your account, or try to access accounts or data that are not yours.",
            "Scrape, crawl or bulk-download Roofly, or use bots to create accounts or join the waitlist.",
            "Attack, overload, probe or bypass the security of the service, or introduce malicious code.",
            "Copy, resell or reverse-engineer Roofly, or use it to build a competing service.",
            "Use Roofly for anything unlawful, including fraud or money laundering.",
          ],
        },
      ],
    },
    {
      id: "reporting",
      heading: "Reporting a problem",
      blocks: [
        {
          type: "p",
          text: "If you see something that breaks these rules, or you find a security weakness, tell us through Help & feedback in the app or using the details below. Please do not test a security weakness beyond what is needed to report it.",
        },
        { type: "contact", channel: "general" },
      ],
    },
    {
      id: "what-we-may-do",
      heading: "What we may do",
      blocks: [
        { type: "p", text: "If these rules are broken, depending on how serious it is we may:" },
        {
          type: "ul",
          items: [
            "contact you and ask you to stop;",
            "issue a formal warning on your account;",
            "remove the content or records concerned;",
            "suspend or close your account;",
            "report the matter to the authorities where the law requires or allows it.",
          ],
        },
        {
          type: "p",
          text: "Where it is reasonable and lawful, we will tell you why and give you a chance to respond.",
        },
      ],
    },
  ],
};

export default doc;
