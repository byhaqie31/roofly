export type TicketStatus = "new" | "in_progress" | "resolved" | "reopened";

export type TicketCategory =
  | "plumbing"
  | "electrical"
  | "appliance"
  | "structural"
  | "pest"
  | "other";

export type TicketPriority = "low" | "medium" | "high" | "urgent";

export type TicketReporterRole = "owner" | "tenant";

export interface TicketComment {
  id: string;
  ticketId: string;
  authorId: string;                // tenant id or "owner-1"
  authorRole: TicketReporterRole;
  body: string;
  createdAt: string;               // ISO datetime
}

export interface Ticket {
  id: string;
  unitId: string;                  // FK -> Unit
  reporterId: string;              // tenant id or "owner-1"
  reporterRole: TicketReporterRole;
  category: TicketCategory;
  priority: TicketPriority;
  title: string;                   // 1-100 char summary, shown on Kanban cards
  description: string;             // longer body, shown on detail
  status: TicketStatus;
  createdAt: string;
  updatedAt: string;
  resolvedAt?: string;             // set when status flips to "resolved"
  // Photos: Phase 4+ (file storage)
}

export type TicketInput = Pick<
  Ticket,
  | "unitId"
  | "reporterId"
  | "reporterRole"
  | "category"
  | "priority"
  | "title"
  | "description"
>;

export type TicketCommentInput = Pick<
  TicketComment,
  "ticketId" | "authorId" | "authorRole" | "body"
>;

// Allowed transitions per the Phase-5 workflow:
//   new          -> in_progress | resolved
//   in_progress  -> resolved | new (regression)
//   resolved     -> reopened
//   reopened     -> in_progress | resolved
export const TICKET_TRANSITIONS: Record<TicketStatus, TicketStatus[]> = {
  new: ["in_progress", "resolved"],
  in_progress: ["resolved", "new"],
  resolved: ["reopened"],
  reopened: ["in_progress", "resolved"],
};
