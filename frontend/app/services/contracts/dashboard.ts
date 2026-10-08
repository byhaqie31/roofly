export type AttentionKind =
  | "payment_claim"
  | "overdue"
  | "expiring"
  | "notice_given"
  | "ticket_new"
  | "ticket_reopened";

export interface AttentionItem {
  kind: AttentionKind;
  title: string;
  meta: string;
  link: string;
}

export interface DashboardStats {
  monthlyIncome: number; // sen
  occupancyPct: number;
  occupiedCount: number;
  unitCount: number;
  outstanding: number; // sen
  outstandingCount: number;
  expiringCount: number;
}

/** Raw server bucket — the localized label is derived on the client. */
export interface IncomeBucket {
  key: string; // YYYY-MM
  amount: number; // sen
}

export interface MonthlyBucket extends IncomeBucket {
  label: string; // localized short month name
}

/** The exact `GET /api/dashboard` payload (also built from demo data in demo). */
export interface DashboardData {
  isEmpty: boolean;
  stats: DashboardStats;
  incomeSeries: IncomeBucket[];
  needsAttention: AttentionItem[];
}

export interface DashboardService {
  getDashboard(): Promise<DashboardData>;
}
