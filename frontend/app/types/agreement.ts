// draft → pending_review (owner sent) → accepted (tenant agreed) → active (owner activated)
// → expired | terminated. Spec: docs/superpowers/specs/2026-10-07-agreement-review-design.md
export type AgreementStatus = "draft" | "pending_review" | "accepted" | "active" | "expired" | "terminated";

export interface Agreement {
  id: string;
  unitId: string;
  tenantId: string;
  startDate: string;          // ISO date (YYYY-MM-DD)
  endDate: string;            // ISO date (YYYY-MM-DD)
  rentAmount: number;         // sen
  depositAmount: number;      // sen
  lateFee: number;            // sen
  rentDueDay: number;         // 1-28
  status: AgreementStatus;
  createdAt: string;
  // Review flow — present on API rows; optional so demo seed data needn't carry them.
  sentAt?: string | null;
  acceptedAt?: string | null;
  changesRequestedAt?: string | null;
  reviewNote?: string | null;
}

export type AgreementInput = Pick<
  Agreement,
  | "unitId"
  | "tenantId"
  | "startDate"
  | "endDate"
  | "rentAmount"
  | "depositAmount"
  | "lateFee"
  | "rentDueDay"
  | "status"
>;

export type AgreementUpdate = Partial<AgreementInput>;
