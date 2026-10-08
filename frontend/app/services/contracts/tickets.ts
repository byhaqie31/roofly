import type {
  Ticket,
  TicketComment,
  TicketCommentInput,
  TicketInput,
  TicketStatus,
} from "~/types/ticket";
import type { Property } from "~/types/property";
import type { Unit } from "~/types/unit";
import type { Tenant } from "~/types/tenant";

export interface TicketWithRefs {
  ticket: Ticket;
  unit: Unit | null;
  property: Property | null;
  reporter: Tenant | null; // null when reporterRole === "owner"
  comments: TicketComment[];
}

export interface TicketsService {
  getTickets(): Promise<Ticket[]>;
  getTicketsWithRefs(): Promise<TicketWithRefs[]>;
  getTicket(id: string): Promise<Ticket | null>;
  getTicketWithRefs(id: string): Promise<TicketWithRefs | null>;
  /** Tenant-shell scope: issues the tenant reported themselves. API: `/me/tickets`. */
  getTicketsForTenant(tenantId: string): Promise<TicketWithRefs[]>;
  create(input: TicketInput): Promise<Ticket>;
  transitionStatus(id: string, next: TicketStatus): Promise<Ticket>;
  addComment(input: TicketCommentInput): Promise<TicketComment>;

  // ── Tenant-shell scope (`/me/tickets/*`) ──────────────────────────────
  /** One of the tenant's own issues, with refs. Null if not theirs. */
  getTicketWithRefsForTenant(id: string): Promise<TicketWithRefs | null>;
  /**
   * File an issue against the tenant's own unit. The API derives unit and
   * reporter from the session and ignores `unitId` / `reporterId` /
   * `reporterRole`; demo needs them to build a full `Ticket`.
   */
  createForTenant(input: TicketInput): Promise<Ticket>;
  /** Comment on one of the tenant's own issues. */
  addCommentForTenant(input: TicketCommentInput): Promise<TicketComment>;
}
