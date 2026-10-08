import type { LegalDocumentContent } from "./types";

// Beta terms — UAT only (useEnv().showBetaTerms). DRAFT for legal review.
const doc: LegalDocumentContent = {
  title: "Beta terms",
  summary:
    "You are using an early version of Roofly as a beta tester. These terms apply on top of our [terms of service](/legal/terms) and [privacy notice](/legal/privacy).",
  sections: [
    {
      id: "as-is",
      heading: "Provided as is",
      blocks: [
        {
          type: "p",
          text: "The beta is provided \"as is\" and \"as available\", free of charge. Features may change, break or be removed, and the service may be unavailable at times. Do not rely on the beta as your only record of anything important.",
        },
      ],
    },
    {
      id: "simulated-payments",
      heading: "Payments are simulated",
      blocks: [
        {
          type: "p",
          text: "No real money moves in the beta. Paying an invoice in the beta only simulates a payment, and a \"paid\" status is a test record. Do not enter real card or bank details anywhere in the beta.",
        },
      ],
    },
    {
      id: "your-data",
      heading: "Your data in the beta",
      blocks: [
        {
          type: "p",
          text: "Data you enter in the beta is personal data and is protected as described in our privacy notice. We may need to correct or reset test data while we fix problems. Where we can, we will tell you before a reset.",
        },
        {
          // TODO(legal): confirm the migration plan and whether testers must opt in.
          type: "p",
          text: "We intend to move beta testers' accounts to the live service when Roofly launches. We will tell you before we do, and you can ask us not to move your account.",
        },
      ],
    },
    {
      id: "feedback",
      heading: "Feedback",
      blocks: [
        {
          type: "p",
          text: "Your feedback shapes Roofly. When you send feedback, bug reports or ideas, through Help & feedback or otherwise, we store them with your name and email so we can follow up, and we may use them to improve Roofly without owing you anything. We will not publish your name or your feedback without your permission.",
        },
      ],
    },
    {
      id: "ending",
      heading: "Ending the beta",
      blocks: [
        {
          type: "p",
          text: "We may end the beta, or your access to it, at any time. You can stop taking part at any time by telling us.",
        },
        { type: "contact", channel: "general" },
      ],
    },
  ],
};

export default doc;
