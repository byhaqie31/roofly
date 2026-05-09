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
import { ticketsMock, ticketCommentsMock } from "~/mocks/tickets";
import { unitsMock } from "~/mocks/units";
import { propertiesMock } from "~/mocks/properties";
import { tenantsMock } from "~/mocks/tenants";

export interface TicketWithRefs {
  ticket: Ticket;
  unit: Unit | null;
  property: Property | null;
  reporter: Tenant | null;       // null when reporterRole === "owner"
  comments: TicketComment[];
}

const hydrate = (t: Ticket): TicketWithRefs => {
  const unit = unitsMock.find((u) => u.id === t.unitId) ?? null;
  const property = unit
    ? (propertiesMock.find((p) => p.id === unit.propertyId) ?? null)
    : null;
  const reporter =
    t.reporterRole === "tenant"
      ? (tenantsMock.find((tn) => tn.id === t.reporterId) ?? null)
      : null;
  const comments = ticketCommentsMock
    .filter((c) => c.ticketId === t.id)
    .sort((a, b) => a.createdAt.localeCompare(b.createdAt));
  return {
    ticket: structuredClone(t),
    unit: unit ? structuredClone(unit) : null,
    property: property ? structuredClone(property) : null,
    reporter: reporter ? structuredClone(reporter) : null,
    comments: structuredClone(comments),
  };
};

export const useTickets = () => {
  const { public: { useMock } } = useRuntimeConfig();

  const list = async (): Promise<Ticket[]> => {
    if (useMock) return structuredClone(ticketsMock);
    const { request } = useApi();
    return request<Ticket[]>("/tickets");
  };

  const listWithRefs = async (): Promise<TicketWithRefs[]> => {
    if (useMock) return ticketsMock.map(hydrate);
    const { request } = useApi();
    return request<TicketWithRefs[]>(
      "/tickets?expand=unit,property,reporter,comments",
    );
  };

  const get = async (id: string): Promise<Ticket | null> => {
    if (useMock) {
      const found = ticketsMock.find((t) => t.id === id);
      return found ? structuredClone(found) : null;
    }
    const { request } = useApi();
    return request<Ticket>(`/tickets/${id}`);
  };

  const getWithRefs = async (id: string): Promise<TicketWithRefs | null> => {
    if (useMock) {
      const found = ticketsMock.find((t) => t.id === id);
      return found ? hydrate(found) : null;
    }
    const { request } = useApi();
    return request<TicketWithRefs>(
      `/tickets/${id}?expand=unit,property,reporter,comments`,
    );
  };

  const create = async (input: TicketInput): Promise<Ticket> => {
    if (useMock) {
      const now = new Date().toISOString();
      const created: Ticket = {
        id: crypto.randomUUID(),
        ...input,
        status: "new",
        createdAt: now,
        updatedAt: now,
      };
      ticketsMock.push(created);
      return structuredClone(created);
    }
    const { request } = useApi();
    return request<Ticket>("/tickets", { method: "POST", body: input });
  };

  const transitionStatus = async (
    id: string,
    next: TicketStatus,
  ): Promise<Ticket> => {
    if (useMock) {
      const idx = ticketsMock.findIndex((t) => t.id === id);
      if (idx === -1) throw new Error(`Ticket ${id} not found`);
      const now = new Date().toISOString();
      const existing = ticketsMock[idx]!;
      const updated: Ticket = {
        ...existing,
        status: next,
        updatedAt: now,
        resolvedAt: next === "resolved" ? now : existing.resolvedAt,
      };
      ticketsMock[idx] = updated;
      return structuredClone(updated);
    }
    const { request } = useApi();
    return request<Ticket>(`/tickets/${id}/status`, {
      method: "PATCH",
      body: { status: next },
    });
  };

  const addComment = async (
    input: TicketCommentInput,
  ): Promise<TicketComment> => {
    if (useMock) {
      const now = new Date().toISOString();
      const created: TicketComment = {
        id: crypto.randomUUID(),
        ...input,
        createdAt: now,
      };
      ticketCommentsMock.push(created);
      // Bump the ticket's updatedAt for sort stability.
      const tIdx = ticketsMock.findIndex((t) => t.id === input.ticketId);
      if (tIdx !== -1) {
        ticketsMock[tIdx] = { ...ticketsMock[tIdx]!, updatedAt: now };
      }
      return structuredClone(created);
    }
    const { request } = useApi();
    return request<TicketComment>(`/tickets/${input.ticketId}/comments`, {
      method: "POST",
      body: { body: input.body },
    });
  };

  return {
    list,
    listWithRefs,
    get,
    getWithRefs,
    create,
    transitionStatus,
    addComment,
  };
};
