import type { AdminAnalyticsService } from "~/services/contracts/admin/analytics";
import type { AdminLead, AdminLeadDetail, AnalyticsOverview } from "~/types/analytics";
import type { Paginated } from "~/types/admin";
import { cleanQuery } from "~/services/api/admin/query";

export const apiAdminAnalytics: AdminAnalyticsService = {
  overview: (range) => useApi().request<AnalyticsOverview>("/admin/analytics/overview", { query: cleanQuery({ ...range }) }),
  leads: (query) => useApi().request<Paginated<AdminLead>>("/admin/analytics/leads", { query: cleanQuery({ ...query }) }),
  lead: async (id) => {
    try {
      return await useApi().request<AdminLeadDetail>(`/admin/analytics/leads/${id}`);
    } catch (e) {
      if ((e as { statusCode?: number })?.statusCode === 404) return null;
      throw e;
    }
  },
  exportCsv: (query) =>
    useApi().request<string>("/admin/analytics/leads/export.csv", { query: cleanQuery({ ...query }), responseType: "text" }),
};
