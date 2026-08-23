import type { AdminDashboardService } from "~/services/contracts/admin/dashboard";
import type { AdminDashboardData } from "~/types/admin";

export const apiAdminDashboard: AdminDashboardService = {
  getDashboard: () => useApi().request<AdminDashboardData>("/admin/dashboard"),
};
