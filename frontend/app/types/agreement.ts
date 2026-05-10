export type AgreementStatus = "draft" | "active" | "expired" | "terminated";

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
