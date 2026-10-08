import type {
  DashboardData,
  DashboardService,
} from "~/services/contracts/dashboard";

export const apiDashboard: DashboardService = {
  getDashboard: () => useApi().request<DashboardData>("/dashboard"),
};
