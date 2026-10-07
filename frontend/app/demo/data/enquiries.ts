import type { AdminEnquiry } from "~/types/support";

const hoursAgo = (h: number) => new Date(Date.now() - h * 3_600_000).toISOString();

/** Seed inbox for the demo adapters (admin is off in demo, so this only backs parity + local mock mode). */
export const enquiriesMock: AdminEnquiry[] = [
  { id: "enq-1", type: "issue", status: "new", message: "The payments page shows last month's invoice as overdue even though Arif paid on the 3rd.", pageUrl: "/owner/payments", pageLabel: "Owner app · Payments", name: "Aminah Yusof", email: "aminah@roofly.my", role: "owner", userId: null, adminNote: null, handledByName: null, statusChangedAt: null, createdAt: hoursAgo(2) },
  { id: "enq-2", type: "question", status: "replied", message: "Can I add a second co-owner after the property is created?", pageUrl: "/owner/properties", pageLabel: "Owner app · Properties", name: "Lim Wei Ling", email: "limlw@example.com", role: "owner", userId: null, adminNote: "Sent the Ownership tab steps.", handledByName: "Ops admin", statusChangedAt: hoursAgo(20), createdAt: hoursAgo(26) },
  { id: "enq-3", type: "feedback", status: "closed", message: "Love the WhatsApp reminders, my tenants actually pay on time now!", pageUrl: "/tenant", pageLabel: "Tenant app · Home", name: "Arif Hakim", email: "arif.hakim@example.com", role: "tenant", userId: null, adminNote: null, handledByName: "Ops admin", statusChangedAt: hoursAgo(70), createdAt: hoursAgo(72) },
];
