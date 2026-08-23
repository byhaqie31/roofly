import type { AdminAnalyticsService } from "~/services/contracts/admin/analytics";
import type { AdminLead, AdminLeadDetail, LeadEvent, LeadListQuery } from "~/types/analytics";
import { analyticsEventsMock, computeOverview, leadVisitorIds, leadsMock } from "~/demo/data/analytics";
import { paginate } from "~/demo/services/admin/paginate";
import { buildCsv } from "~/utils/csv";

const filterLeads = (query: LeadListQuery): AdminLead[] => {
  let rows = [...leadsMock].sort((a, b) => b.lastSeenAt.localeCompare(a.lastSeenAt));
  const q = query.q?.trim().toLowerCase();
  if (q) rows = rows.filter((l) => l.email.toLowerCase().includes(q));
  if (query.source) rows = rows.filter((l) => l.source === query.source);
  if (query.converted) rows = rows.filter((l) => l.convertedUserId !== null);
  return rows;
};

export const demoAdminAnalytics: AdminAnalyticsService = {
  async overview(range) {
    return structuredClone(computeOverview(range));
  },

  async leads(query) {
    const rows = filterLeads(query);
    return structuredClone(paginate(rows, query.page, query.perPage));
  },

  async lead(id) {
    const lead = leadsMock.find((l) => l.id === id);
    if (!lead) return null;
    const visitorId = leadVisitorIds[id];
    const events: LeadEvent[] = visitorId
      ? analyticsEventsMock
          .filter((e) => e.visitorId === visitorId)
          .sort((a, b) => b.createdAt.localeCompare(a.createdAt))
          .slice(0, 20)
          .map((e, i) => ({ id: `${id}-evt-${i}`, event: e.event, path: e.path, props: e.props, createdAt: e.createdAt }))
      : [];
    const detail: AdminLeadDetail = { ...lead, events };
    return structuredClone(detail);
  },

  async exportCsv(query) {
    const rows = filterLeads(query);
    return buildCsv(
      ["email", "source", "firstSeenAt", "lastSeenAt", "pageViews", "demoEntered", "convertedOwnerName"],
      rows.map((l) => [l.email, l.source, l.firstSeenAt, l.lastSeenAt, l.pageViews, l.demoEntered ? "yes" : "no", l.convertedOwnerName]),
    );
  },
};
