import type { LegalDocumentContent } from "./types";

// Privacy notice under the Personal Data Protection Act 2010 (as amended in 2024).
// DRAFT for legal review — describes only what is built today. Verified against
// backend migrations/models, AdminOwnerResource/AdminTenantResource,
// AnalyticsRecorder, utils/trackedPaths.ts and .env.example (2026-10-08).
const doc: LegalDocumentContent = {
  title: "Privacy notice",
  summary:
    "This notice explains what personal data Roofly holds, why we hold it, who we share it with and how you can access or correct it. It applies to roofly.my and its subdomains.",
  sections: [
    {
      id: "who-we-are",
      heading: "Who we are",
      blocks: [
        {
          type: "p",
          text: "Roofly is a rent-management service for landlords in Malaysia and their tenants. Roofly is a product of Axel Nova Ventures, which operates the service. In this notice, \"we\" and \"us\" mean Axel Nova Ventures.",
        },
        { type: "contact", channel: "privacy" },
      ],
    },
    {
      id: "our-roles",
      heading: "Our two roles",
      blocks: [
        {
          type: "p",
          text: "We are the data controller for the data we collect for ourselves: owner accounts, tenant sign-in details, the waitlist, support messages, analytics and security logs.",
        },
        {
          type: "p",
          text: "When an owner adds a tenant and records the tenancy (the tenant's details, agreement, rent invoices, payments and maintenance issues), the owner decides why and how that data is used. For that data the owner is the data controller and we act as a data processor on the owner's behalf. Tenants who want to know how their landlord uses their data should also ask their landlord.",
        },
      ],
    },
    {
      id: "data-we-hold",
      heading: "Personal data we hold",
      blocks: [
        { type: "p", text: "Depending on how you use Roofly, we hold the following." },
        {
          type: "ul",
          items: [
            "Owners: name, email address, phone number, password (stored only as a one-way hash), business name and the last four digits of a bank account if you add them, plan, preferences, and the properties, units and co-owners (name and ownership share) you record, including title, purchase and valuation figures you choose to enter.",
            "Owners who use Google sign-in: your Google account ID, name, email address and the link to your Google profile picture.",
            "Tenants: name, email address, phone number, password hash, MyKad number, date of birth, nationality, occupation, employer and monthly income where provided, emergency contact (name, phone number and relationship), and the tenancy records linked to you: agreements, rent invoices, payment records, maintenance issues and comments.",
            "Waitlist: your email address, the date you joined, and whether you later received an invitation or registered.",
            "Support messages: your name, email address, account type, the message you send, the page you sent it from and your browser details, plus our internal notes on the message.",
            "Analytics: page views and a few actions on our public pages, described under \"Analytics and browser storage\".",
            "Security and activity logs: sign-in sessions (IP address and browser details), a record of changes made to accounts and tenancy records, and a record of actions taken by our staff, including their IP address.",
          ],
        },
        {
          type: "p",
          text: "Payments in Roofly are simulated today. We do not collect card numbers or online banking details.",
        },
      ],
    },
    {
      id: "sources",
      heading: "Where the data comes from",
      blocks: [
        {
          type: "ul",
          items: [
            "From you, when you join the waitlist, create an account, fill in your profile, use the app or contact us.",
            "From an owner, when they invite you as a tenant or record your tenancy.",
            "From Google, when an owner chooses to sign in with Google.",
            "From your browser, when you visit our public pages or sign in (see \"Analytics and browser storage\").",
          ],
        },
      ],
    },
    {
      id: "purposes",
      heading: "Why we use it",
      blocks: [
        {
          type: "ul",
          items: [
            "To create and run your account and sign you in.",
            "To let owners manage properties, tenancies, agreements, rent invoices, payment records and maintenance issues, and to let tenants see and act on their own tenancy.",
            "To send service emails, such as invitations, password resets, waitlist and welcome emails, and replies to your messages.",
            "To answer support messages and improve Roofly based on feedback.",
            "To understand how visitors find and use our public pages.",
            "To keep the service secure, investigate misuse and keep an audit trail of staff actions.",
            "To meet our legal obligations.",
          ],
        },
        {
          type: "p",
          text: "We do not sell personal data and we do not use it for third-party advertising.",
        },
      ],
    },
    {
      id: "obligatory",
      heading: "Do you have to give us this data?",
      blocks: [
        {
          type: "p",
          text: "Your name and email address are obligatory to create an account, and an email address is obligatory to join the waitlist. Without them we cannot provide the account or contact you.",
        },
        {
          type: "p",
          text: "Tenants are asked for a phone number, MyKad number and an emergency contact before they can use the tenant app, because owners need these to manage a tenancy. If you do not provide them, you will not be able to use the tenant app. Everything else, such as date of birth, occupation, employer and income, is voluntary.",
        },
      ],
    },
    {
      id: "tracking",
      heading: "Analytics and browser storage",
      blocks: [
        {
          type: "p",
          text: "We run our own analytics. They run only on our public pages: the home and coming-soon pages, the demo, the sign-in and sign-up pages and these legal pages. They never run inside the owner, tenant or admin areas of the app.",
        },
        {
          type: "p",
          text: "On those pages we record page views and a few actions (opening the demo, joining the waitlist, registering and sending demo feedback), together with the page address, the referring site, campaign tags in the link you followed, your browser details and a scrambled (hashed) form of your IP address. To link visits together we keep a random visitor ID in your browser's local storage.",
        },
        {
          type: "p",
          text: "We do not use third-party analytics or advertising cookies. The only cookies we set keep you signed in, protect forms against cross-site request forgery and remember your language. Your browser also stores your theme choice. You can clear all of these in your browser settings at any time.",
        },
      ],
    },
    {
      id: "sharing",
      heading: "Who we share it with",
      blocks: [
        {
          type: "p",
          text: "We share personal data only with the following classes of third parties, and only as far as each needs it:",
        },
        {
          type: "ul",
          items: [
            // TODO(legal): confirm Cloudflare's role (DNS only, or proxy/CDN too) and the production email provider (Resend is configured, not confirmed live).
            "Service providers who run Roofly for us: our server hosting provider (Hostinger), our domain name and network provider (Cloudflare) and our email delivery provider.",
            "Google, when an owner chooses Google sign-in. On sign-in pages where Google sign-in is offered, your browser loads Google's sign-in script.",
            "The other side of a tenancy: an owner sees the details of the tenants they manage, and a tenant sees their own agreement, invoices and issues, including the owner's name.",
            "Authorities, courts or regulators, where the law requires it.",
            // TODO(legal): standard business-transfer clause; confirm it's wanted.
            "A buyer or successor of the business, if Roofly is sold or restructured, under the same protections.",
          ],
        },
        {
          type: "p",
          text: "When real payments are introduced, a payment gateway will process them. We will update this notice before that happens.",
        },
      ],
    },
    {
      id: "transfers",
      heading: "Where your data is stored",
      blocks: [
        {
          // TODO(legal): confirm the Hostinger VPS data-centre location and whether personal data leaves Malaysia; rewrite this section with the answer.
          type: "p",
          text: "Our servers are run by our hosting provider. Some of our service providers may process data outside Malaysia. Where personal data is transferred outside Malaysia, we take steps to make sure it is protected to a standard comparable to the Personal Data Protection Act 2010.",
        },
      ],
    },
    {
      id: "retention",
      heading: "How long we keep it",
      blocks: [
        {
          type: "p",
          text: "When an account, property, unit, agreement, invoice or maintenance issue is deleted in Roofly, it is first hidden rather than erased immediately, so that mistakes can be undone and records stay consistent. Waitlist entries, support messages, analytics events and activity logs are kept until we remove them.",
        },
        {
          // TODO(legal): set the retention period for each category and how permanent erasure happens.
          type: "p",
          text: "We keep personal data only as long as we need it for the purposes in this notice or as the law requires, and then delete or anonymise it.",
        },
      ],
    },
    {
      id: "rights",
      heading: "Your rights",
      blocks: [
        { type: "p", text: "Under the Personal Data Protection Act 2010 you can:" },
        {
          type: "ul",
          items: [
            "Ask for access to the personal data we hold about you and for a copy of it.",
            "Ask us to correct personal data that is inaccurate, incomplete, misleading or out of date. Much of it you can also correct yourself on your profile or settings page.",
            "Withdraw your consent to processing, or ask us to limit it, including asking us not to send you emails you did not ask for. This may mean we cannot keep providing the service.",
            "Ask for your data in a commonly used, machine-readable format so you can move it to another service (data portability).",
          ],
        },
        {
          type: "p",
          text: "To make a request, email our privacy contact below, or send us a message through Help & feedback in the app. We may need to confirm your identity first and will reply within the time the law allows. If your request concerns tenancy data an owner controls, we will work with that owner to answer it.",
        },
        { type: "contact", channel: "privacy" },
      ],
    },
    {
      id: "staff-access",
      heading: "Who at Roofly can see it",
      blocks: [
        {
          type: "p",
          text: "Our admin staff see only what they need to run accounts. For owners: name, email address, business name, plan, account status, record counts and each property's name, city and state. For tenants, whose details belong to the owner: a shortened name (such as \"Aminah Y.\") and a partly hidden email address, their status, and which owner, property and unit they are linked to. Staff also see waitlist emails with their visit history on our public pages, and the support messages people send us.",
        },
        {
          type: "p",
          text: "Admin staff do not see phone numbers, full tenant names or email addresses, MyKad numbers, other personal details, emergency contacts, street addresses, rent amounts, invoices or payments. Staff access is limited by role, and staff actions are logged.",
        },
      ],
    },
    {
      id: "security",
      heading: "Security and data breaches",
      blocks: [
        {
          type: "p",
          text: "We protect personal data with technical and organisational measures, including encrypted connections, hashed passwords, role-based access and activity logs, and our data processors must protect it too. If a personal data breach happens, we will notify the Personal Data Protection Commissioner and the people affected where the law requires.",
        },
      ],
    },
    {
      id: "changes",
      heading: "Changes and language",
      blocks: [
        {
          type: "p",
          text: "We will update this notice when what we do with personal data changes, and show the new version number and effective date at the top. For significant changes we will also tell account holders by email or in the app.",
        },
        {
          // TODO(legal): confirm which language version prevails.
          type: "p",
          text: "This notice is available in English and Bahasa Malaysia. If the two versions differ, the English version prevails.",
        },
      ],
    },
    {
      id: "contact",
      heading: "Contact us",
      blocks: [
        {
          type: "p",
          text: "For any question about this notice or your personal data, contact us using the details below.",
        },
        { type: "contact", channel: "privacy" },
      ],
    },
  ],
};

export default doc;
