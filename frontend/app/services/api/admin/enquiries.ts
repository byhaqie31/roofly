import type { AdminEnquiriesService } from "~/services/contracts/admin/enquiries";
import type { AdminEnquiry, AdminEnquiryPage } from "~/types/support";
import { cleanQuery } from "~/services/api/admin/query";

export const apiAdminEnquiries: AdminEnquiriesService = {
  list: (query) => useApi().request<AdminEnquiryPage>("/admin/enquiries", { query: cleanQuery({ ...query }) }),
  update: (id, patch) => useApi().request<AdminEnquiry>(`/admin/enquiries/${id}`, { method: "PATCH", body: patch }),
};
