import type { AdminEnquiry, AdminEnquiryPage, AdminEnquiryPatch, AdminEnquiryQuery } from "~/types/support";

/** Admin → Enquiries → Messages (support.manage). Track only — no in-app replies. */
export interface AdminEnquiriesService {
  list(query: AdminEnquiryQuery): Promise<AdminEnquiryPage>;
  update(id: string, patch: AdminEnquiryPatch): Promise<AdminEnquiry>;
}
