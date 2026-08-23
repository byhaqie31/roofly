import type {
  AdminLead, AdminLeadDetail, AnalyticsOverview, AnalyticsRange, LeadListQuery,
} from "~/types/analytics";
import type { Paginated } from "~/types/admin";

export interface AdminAnalyticsService {
  overview(range: AnalyticsRange): Promise<AnalyticsOverview>;
  leads(query: LeadListQuery): Promise<Paginated<AdminLead>>;
  lead(id: string): Promise<AdminLeadDetail | null>;
  exportCsv(query: LeadListQuery): Promise<string>;
}
