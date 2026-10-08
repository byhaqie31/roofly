import type { AdminEnquiriesService } from "~/services/contracts/admin/enquiries";
import { demoAdminEnquiries } from "~/demo/services/admin/enquiries";
import { apiAdminEnquiries } from "~/services/api/admin/enquiries";

export const useAdminEnquiries = (): AdminEnquiriesService =>
  useEnv().useMock ? demoAdminEnquiries : apiAdminEnquiries;
