import type { Ticket, TicketComment } from "~/types/ticket";
import type {
  TicketsService,
  TicketWithRefs,
} from "~/services/contracts/tickets";
import { ticketsMock, ticketCommentsMock } from "~/demo/data/tickets";
import { unitsMock } from "~/demo/data/units";
import { propertiesMock } from "~/demo/data/properties";
import { tenantsMock } from "~/demo/data/tenants";

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

export const demoTickets: TicketsService = {
  async getTickets() {
    return structuredClone(ticketsMock);
  },

  async getTicketsWithRefs() {
    return ticketsMock.map(hydrate);
  },

  async getTicket(id) {
    const found = ticketsMock.find((t) => t.id === id);
    return found ? structuredClone(found) : null;
  },

  async getTicketWithRefs(id) {
    const found = ticketsMock.find((t) => t.id === id);
    return found ? hydrate(found) : null;
  },

  async getTicketsForTenant(tenantId) {
    return ticketsMock
      .filter((t) => t.reporterRole === "tenant" && t.reporterId === tenantId)
      .map(hydrate);
  },

  async create(input) {
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
  },

  async transitionStatus(id, next) {
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
  },

  async addComment(input) {
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
  },

  // ── Tenant-shell scope: same in-memory world, no role gate in demo ─────
  async getTicketWithRefsForTenant(id) {
    const found = ticketsMock.find((t) => t.id === id);
    return found ? hydrate(found) : null;
  },

  async createForTenant(input) {
    return demoTickets.create(input);
  },

  async addCommentForTenant(input) {
    return demoTickets.addComment(input);
  },
};
