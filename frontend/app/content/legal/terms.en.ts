import type { LegalDocumentContent } from "./types";

// Terms of service. DRAFT for legal review — describes only what is built today
// (payments simulated; no file uploads, WhatsApp or automated reminders yet).
const doc: LegalDocumentContent = {
  title: "Terms of service",
  summary:
    "These terms are the agreement between you and Axel Nova Ventures for using Roofly. Please read them together with our [privacy notice](/legal/privacy) and [acceptable use policy](/legal/acceptable-use).",
  sections: [
    {
      id: "about",
      heading: "About these terms",
      blocks: [
        {
          type: "p",
          text: "Roofly is provided by Axel Nova Ventures (\"we\", \"us\"). By creating an account, accepting an invitation or otherwise using Roofly, you agree to these terms. Accepting them online is as binding as signing them on paper.",
        },
        {
          // TODO(legal): confirm the eligibility rule (age, capacity, Malaysia-only or not).
          type: "p",
          text: "You must be at least 18 years old and able to enter into a binding contract to use Roofly. If you use Roofly for a business, you confirm you are authorised to accept these terms for it.",
        },
      ],
    },
    {
      id: "accounts",
      heading: "Accounts and roles",
      blocks: [
        {
          type: "ul",
          items: [
            "Owners sign up for themselves, with an email address and password or with Google sign-in, and manage their properties, tenants and tenancies.",
            "Tenants join only when an owner invites them. A tenant can see and act on their own tenancy: their agreement, invoices, payments and maintenance issues.",
            "You are responsible for keeping your sign-in details secure and for what happens under your account. Tell us straight away if you think someone else has used it.",
            "The details you give us must be accurate, and you should keep them up to date.",
          ],
        },
      ],
    },
    {
      id: "plans",
      heading: "Plans and prices",
      blocks: [
        {
          type: "p",
          text: "Roofly plans differ by how many units you can manage. These are the planned monthly prices:",
        },
        { type: "plans" },
        {
          type: "p",
          text: "Roofly is free during the beta and nobody is charged. A free plan will remain after the beta. Before any billing starts we will publish the renewal, cancellation, downgrade and refund terms in our [billing and refunds policy](/legal/billing) and tell account holders in advance.",
        },
      ],
    },
    {
      id: "what-roofly-is-not",
      heading: "What Roofly is not",
      blocks: [
        {
          type: "p",
          text: "Roofly is a tool that helps owners and tenants keep records and communicate. It is not:",
        },
        {
          type: "ul",
          items: [
            "a party to any tenancy. The tenancy is between the owner and the tenant, and is governed by their tenancy agreement and the general law of Malaysia, including the Contracts Act 1950;",
            "a property or estate agent, and it does not find, vet or recommend tenants or properties;",
            "a law firm, and nothing in Roofly is legal advice;",
            "a tax adviser, and nothing in Roofly is tax advice.",
          ],
        },
      ],
    },
    {
      id: "agreements",
      heading: "Tenancy agreements and stamping",
      blocks: [
        {
          type: "p",
          text: "Roofly helps an owner record the terms of a tenancy and send them to the tenant to review and accept. The wording, fields and structure Roofly offers are a convenience, not legal advice, and may not suit every tenancy. Get independent legal advice if you are unsure.",
        },
        {
          type: "p",
          text: "When a tenant accepts an agreement in Roofly, Roofly records that acceptance. Whether and how the agreement binds the owner and the tenant is a matter between them. Stamping the tenancy agreement with LHDN, and paying any stamp duty, is the responsibility of the owner and tenant, not Roofly.",
        },
      ],
    },
    {
      id: "reports",
      heading: "Reports and tax figures",
      blocks: [
        {
          type: "p",
          text: "Reports, income summaries and real property gains tax (RPGT) figures are estimates based on the data you enter and simplified rules. They are not tax advice and may be wrong for your situation. Check with a tax adviser or LHDN before relying on them.",
        },
      ],
    },
    {
      id: "payments",
      heading: "Payments",
      blocks: [
        {
          type: "p",
          text: "Payments in Roofly are simulated today: no real money moves through Roofly, and a payment marked as paid in the app is a record only. Owners can also record payments they received outside Roofly.",
        },
        {
          type: "p",
          text: "When real online payments launch, they will be processed by a third-party payment gateway under its own terms. Roofly will not hold rent money. We will update these terms before that happens.",
        },
      ],
    },
    {
      id: "owner-responsibilities",
      heading: "Owner responsibilities",
      blocks: [
        {
          type: "ul",
          items: [
            "You must have the right to give us your tenants' personal data, and to have us process it for you. For tenancy data you enter, you are the data controller under the Personal Data Protection Act 2010 and we process it on your behalf, as explained in our [privacy notice](/legal/privacy).",
            "You must tell your tenants that you use Roofly to manage their tenancy, and handle their requests about their data.",
            "You are responsible for the accuracy of the properties, tenancies, invoices and payment records you create, and for complying with the law that applies to your tenancies.",
          ],
        },
      ],
    },
    {
      id: "acceptable-use",
      heading: "Acceptable use",
      blocks: [
        {
          type: "p",
          text: "Use Roofly lawfully and fairly. Do not create fake tenants or properties, harass or abuse anyone through issues or comments, enter other people's data without the right to, or scrape, overload or attack the service. The full rules, and what we may do if they are broken, are in our [acceptable use policy](/legal/acceptable-use).",
        },
      ],
    },
    {
      id: "content-and-ip",
      heading: "Your data and our intellectual property",
      blocks: [
        {
          type: "p",
          text: "The data you put into Roofly stays yours. You allow us to store and process it only to provide and improve the service and as described in our privacy notice.",
        },
        {
          type: "p",
          text: "Roofly itself (its software, design, name and logo) belongs to Axel Nova Ventures. You may use it as these terms allow, but you may not copy, resell or reverse-engineer it. If you send us feedback or ideas, we may use them without owing you anything.",
        },
      ],
    },
    {
      id: "availability",
      heading: "Availability and beta",
      blocks: [
        {
          type: "p",
          text: "We work to keep Roofly running, but it may sometimes be unavailable for maintenance, updates or reasons outside our control. We may change, add or remove features. Some features are marked as coming soon and are not available yet.",
        },
        {
          type: "p",
          text: "During the beta Roofly is provided \"as is\" and \"as available\", and may contain errors. Keep your own copies of anything important.",
        },
      ],
    },
    {
      id: "liability",
      heading: "Our liability",
      blocks: [
        {
          // TODO(legal): draft liability limits that respect the Consumer Protection Act 1999 and its unfair-terms rules; this is a placeholder.
          type: "p",
          text: "Nothing in these terms limits any right you have under the Consumer Protection Act 1999 or any other law that cannot be excluded. Subject to that, we are not responsible for losses caused by the tenancy itself, by what owners and tenants do, by decisions made relying on estimates or records in Roofly, or by events outside our reasonable control.",
        },
      ],
    },
    {
      id: "ending",
      heading: "Ending your account and exporting data",
      blocks: [
        {
          type: "p",
          text: "You can stop using Roofly at any time. To close your account, contact us. Owners can download their yearly report as a CSV file from the Reports page, and anyone can ask us for a copy of their data as described in our privacy notice.",
        },
        {
          type: "p",
          text: "We may suspend or close an account that breaks these terms or the acceptable use policy, or where the law requires it. Where it is reasonable, we will tell you first and give you a chance to export your data.",
        },
      ],
    },
    {
      id: "changes",
      heading: "Changes to these terms",
      blocks: [
        {
          type: "p",
          text: "We may update these terms. The version number and effective date at the top show the current version. For significant changes we will tell account holders by email or in the app before they take effect. If you keep using Roofly after that, the new terms apply.",
        },
      ],
    },
    {
      id: "law",
      heading: "Governing law",
      blocks: [
        {
          // TODO(legal): confirm governing law, jurisdiction and prevailing language.
          type: "p",
          text: "These terms are governed by the laws of Malaysia, and the courts of Malaysia have jurisdiction. These terms are available in English and Bahasa Malaysia. If the two versions differ, the English version prevails.",
        },
      ],
    },
    {
      id: "contact",
      heading: "Contact us",
      blocks: [{ type: "contact", channel: "general" }],
    },
  ],
};

export default doc;
