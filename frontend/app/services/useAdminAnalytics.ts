import type { AdminAnalyticsService } from "~/services/contracts/admin/analytics";
import { demoAdminAnalytics } from "~/demo/services/admin/analytics";
import { apiAdminAnalytics } from "~/services/api/admin/analytics";

export const useAdminAnalytics = (): AdminAnalyticsService =>
  useEnv().useMock ? demoAdminAnalytics : apiAdminAnalytics;
