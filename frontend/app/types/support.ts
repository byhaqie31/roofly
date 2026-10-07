// In-app help button (owners + tenants) → admin Enquiries → Messages.

export type EnquiryType = "issue" | "feedback" | "question";
export type EnquiryStatus = "new" | "replied" | "closed";

export const ENQUIRY_TYPES: EnquiryType[] = ["issue", "feedback", "question"];
export const ENQUIRY_STATUSES: EnquiryStatus[] = ["new", "replied", "closed"];

/** What the help form sends. Name/email/role come from the session, never the form. */
export interface SupportEnquiryInput {
  type: EnquiryType;
  message: string;
  /** The page the user was on when they opened the form (path + query). */
  pageUrl?: string;
  /** Readable name for that page ("Owner app · Payments"), from utils/pageLabel. */
  pageLabel?: string;
}

export interface AdminEnquiry {
  id: string;
  type: EnquiryType;
  status: EnquiryStatus;
  message: string;
  pageUrl: string | null;
  pageLabel: string | null;
  name: string;
  email: string;
  role: "owner" | "tenant" | null;
  userId: string | null;
  adminNote: string | null;
  handledByName: string | null;
  statusChangedAt: string | null;
  createdAt: string;
}

export interface AdminEnquiryQuery {
  q?: string;
  status?: EnquiryStatus;
  type?: EnquiryType;
  page?: number;
  perPage?: number;
}

export interface AdminEnquiryPage {
  data: AdminEnquiry[];
  meta: { page: number; perPage: number; total: number; lastPage: number; newCount: number };
}

export interface AdminEnquiryPatch {
  status?: EnquiryStatus;
  adminNote?: string | null;
}
