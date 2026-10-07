import type { SupportEnquiryInput } from "~/types/support";

export type { SupportEnquiryInput } from "~/types/support";

/** The floating help button's one call: owner or tenant sends an issue / feedback / question. */
export interface SupportService {
  send(input: SupportEnquiryInput): Promise<void>;
}
