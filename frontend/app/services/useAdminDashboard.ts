import type { AdminDashboardService } from "~/services/contracts/admin/dashboard";
import { demoAdminDashboard } from "~/demo/services/admin/dashboard";
import { apiAdminDashboard } from "~/services/api/admin/dashboard";

/** Demo → fake platform; otherwise the Laravel API. Chosen once per call. */
export const useAdminDashboard = (): AdminDashboardService =>
  useEnv().useMock ? demoAdminDashboard : apiAdminDashboard;
