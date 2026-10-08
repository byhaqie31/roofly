import type { AdminDashboardData } from "~/types/admin";

export interface AdminDashboardService {
  getDashboard(): Promise<AdminDashboardData>;
}
