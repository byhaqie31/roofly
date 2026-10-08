import type {
  AdminLead, AdminLeadDetail, AnalyticsOverview, AnalyticsRange, LeadListQuery,
} from "~/types/analytics";
import type { Paginated } from "~/types/admin";

export interface AdminAnalyticsService {
  overview(range: AnalyticsRange): Promise<AnalyticsOverview>;
  leads(query: LeadListQuery): Promise<Paginated<AdminLead>>;
  lead(id: string): Promise<AdminLeadDetail | null>;
  exportCsv(query: LeadListQuery): Promise<string>;
  /** Emails a waitlist lead the sign-up invitation (re-send allowed); returns the updated lead. */
  invite(id: string): Promise<AdminLead>;
}
