export const TRACK_EVENTS = ["page_view", "demo_enter", "demo_feedback_click", "waitlist_signup", "register"] as const;
export type TrackEvent = (typeof TRACK_EVENTS)[number];

export interface TrackPayload {
  visitorId: string;
  event: TrackEvent;
  path?: string;
  referrer?: string;
  utm?: { source?: string; medium?: string; campaign?: string };
  props?: Record<string, string>;
  at: string;
}

export interface TrackAdapter {
  send(payload: TrackPayload): void;
}

// ── Admin analytics (spec § 5) ──────────────────────────────────────────
export type LeadSource = "waitlist" | "demo" | "register";

export interface AdminLead {
  id: string;
  email: string;
  source: LeadSource;
  firstSeenAt: string;
  lastSeenAt: string;
  pageViews: number;
  demoEntered: boolean;
  convertedUserId: string | null;
  convertedOwnerName: string | null;
}

export interface LeadEvent {
  id: string;
  event: TrackEvent;
  path: string | null;
  props: Record<string, unknown>;
  createdAt: string;
}

export interface AdminLeadDetail extends AdminLead {
  events: LeadEvent[];
}

export interface AnalyticsRange {
  from?: string;
  to?: string;
}

export interface AnalyticsOverview {
  range: { from: string; to: string; days: number };
  tiles: {
    views: number;
    visitors: number;
    newVisitors: number;
    demoEntries: number;
    leads: number;
    registrations: number;
    conversionPct: number;
  };
  series: {
    days: string[];
    views: number[];
    visitors: number[];
    leads: number[];
    registrations: number[];
  };
  funnel: { visitors: number; demo: number; leads: number; registered: number };
  topPages: { path: string; views: number }[];
  referrers: { referrer: string; visitors: number }[];
}

export interface LeadListQuery {
  q?: string;
  source?: LeadSource;
  converted?: boolean;
  page?: number;
  perPage?: number;
}
