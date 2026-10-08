import type { LegalDocumentContent } from "./types";

// Billing and refunds. DRAFT — true to today: free during beta, nobody charged.
//
// TODO(legal): before billing starts, add these sections (both locales):
//   - billing cycle and renewal (monthly, auto-renew, reminder before renewal)
//   - accepted payment methods and the payment gateway
//   - price in RM, inclusive or exclusive of SST
//   - upgrades (prorated?) and downgrades (what happens to units over the cap)
//   - cancellation (effective end of period?) and data access afterwards
//   - refunds (when they apply, how to ask, how long they take)
//   - failed payments and grace period
//   - price changes and notice period
const doc: LegalDocumentContent = {
  title: "Billing and refunds",
  summary: "Roofly is free during the beta. Nobody is charged, and we do not collect any payment details.",
  sections: [
    {
      id: "today",
      heading: "Free during the beta",
      blocks: [
        {
          type: "p",
          text: "While Roofly is in beta, every account can use it at no cost. We do not ask for card or bank details, and no subscription is charged or renewed.",
        },
      ],
    },
    {
      id: "plans",
      heading: "Planned plans and prices",
      blocks: [
        {
          type: "p",
          text: "After the beta, Roofly plans will differ by how many units you can manage. These are the planned monthly prices in Malaysian ringgit:",
        },
        { type: "plans" },
        {
          type: "p",
          text: "A free plan will remain after the beta. Planned prices may change before billing starts.",
        },
      ],
    },
    {
      id: "before-billing",
      heading: "Before any billing starts",
      blocks: [
        {
          type: "p",
          text: "Before we charge anyone, we will publish on this page the full terms for renewal, cancellation, downgrades and refunds, the payment methods we accept and how payments are processed. We will tell account holders in advance, and nobody will be moved to a paid plan without choosing it.",
        },
      ],
    },
    {
      id: "rent",
      heading: "Rent is not billed by Roofly",
      blocks: [
        {
          type: "p",
          text: "Rent invoices in Roofly are between owners and tenants. Payments in Roofly are simulated today, and Roofly does not collect or hold rent. See our [terms of service](/legal/terms) for more.",
        },
      ],
    },
    {
      id: "contact",
      heading: "Questions",
      blocks: [
        { type: "p", text: "For questions about plans or billing, contact us using the details below." },
        { type: "contact", channel: "general" },
      ],
    },
  ],
};

export default doc;
