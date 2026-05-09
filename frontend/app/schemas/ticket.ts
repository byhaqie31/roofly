import { z } from "zod";

const ticketStatusSchema = z.enum([
  "new",
  "in_progress",
  "resolved",
  "reopened",
]);

const ticketCategorySchema = z.enum([
  "plumbing",
  "electrical",
  "appliance",
  "structural",
  "pest",
  "other",
]);

const ticketPrioritySchema = z.enum(["low", "medium", "high", "urgent"]);

const reporterRoleSchema = z.enum(["owner", "tenant"]);

// Create-ticket form schema (owner-side modal). reporterId/reporterRole are
// injected by the page based on selection — not part of the form fields.
export const ticketCreateFormSchema = z.object({
  unitId: z.string().min(1, "Pick a unit"),
  category: ticketCategorySchema,
  priority: ticketPrioritySchema,
  title: z.string().min(3, "Min 3 chars").max(100),
  description: z.string().min(5, "Min 5 chars").max(2000),
  reporterId: z.string().min(1),
  reporterRole: reporterRoleSchema,
});

export const ticketCommentFormSchema = z.object({
  body: z.string().min(1, "Comment can't be empty").max(2000),
});

export const ticketStatusTransitionSchema = z.object({
  status: ticketStatusSchema,
});

export type TicketCreateDto = z.infer<typeof ticketCreateFormSchema>;
export type TicketCommentDto = z.infer<typeof ticketCommentFormSchema>;
