import type { Ticket, TicketComment } from "~/types/ticket";
import type {
  TicketsService,
  TicketWithRefs,
} from "~/services/contracts/tickets";

const EXPAND = "expand=unit,property,reporter,comments";

export const apiTickets: TicketsService = {
  getTickets: () => useApi().request<Ticket[]>("/tickets"),

  getTicketsWithRefs: () =>
    useApi().request<TicketWithRefs[]>(`/tickets?${EXPAND}`),

  getTicket: (id) => useApi().request<Ticket>(`/tickets/${id}`),

  getTicketWithRefs: (id) =>
    useApi().request<TicketWithRefs>(`/tickets/${id}?${EXPAND}`),

  getTicketsForTenant: () =>
    useApi().request<TicketWithRefs[]>(`/me/tickets?${EXPAND}`),

  create: (input) =>
    useApi().request<Ticket>("/tickets", { method: "POST", body: input }),

  transitionStatus: (id, next) =>
    useApi().request<Ticket>(`/tickets/${id}/status`, {
      method: "PATCH",
      body: { status: next },
    }),

  addComment: (input) =>
    useApi().request<TicketComment>(`/tickets/${input.ticketId}/comments`, {
      method: "POST",
      body: { body: input.body },
    }),

  // ── Tenant-shell scope — server derives unit/reporter from the session ──
  getTicketWithRefsForTenant: (id) =>
    useApi().request<TicketWithRefs>(`/me/tickets/${id}?${EXPAND}`),

  createForTenant: ({ category, priority, title, description }) =>
    useApi().request<Ticket>("/me/tickets", {
      method: "POST",
      body: { category, priority, title, description },
    }),

  addCommentForTenant: (input) =>
    useApi().request<TicketComment>(`/me/tickets/${input.ticketId}/comments`, {
      method: "POST",
      body: { body: input.body },
    }),
};
