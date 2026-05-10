import type { Agreement } from "~/types/agreement";

export const agreementsMock: Agreement[] = [
  {
    id: "a-suria-aminah",
    unitId: "u-suria-1",
    tenantId: "t-aminah",
    startDate: "2025-09-01",
    endDate: "2026-08-31",
    rentAmount: 350_000,         // RM 3,500
    depositAmount: 700_000,      // 2 months
    lateFee: 5_000,              // RM 50
    rentDueDay: 1,
    status: "active",
    createdAt: "2025-08-25T10:00:00Z",
  },
  {
    id: "a-wangsa-arif",
    unitId: "u-wangsa-1",
    tenantId: "t-arif",
    startDate: "2025-12-01",
    endDate: "2026-11-30",
    rentAmount: 400_000,         // RM 4,000
    depositAmount: 1_200_000,    // 3 months commercial
    lateFee: 10_000,             // RM 100
    rentDueDay: 5,
    status: "active",
    createdAt: "2025-11-20T09:30:00Z",
  },
  {
    id: "a-ttdi-liwei",
    unitId: "u-ttdi-1",
    tenantId: "t-li-wei",
    startDate: "2026-06-01",
    endDate: "2027-05-31",
    rentAmount: 320_000,         // RM 3,200
    depositAmount: 640_000,
    lateFee: 5_000,
    rentDueDay: 1,
    status: "draft",
    createdAt: "2026-04-30T15:00:00Z",
  },
  {
    id: "a-usj-ravi",
    unitId: "u-usj-1",
    tenantId: "t-ravi",
    startDate: "2024-04-01",
    endDate: "2025-03-31",
    rentAmount: 80_000,          // RM 800
    depositAmount: 160_000,
    lateFee: 2_000,
    rentDueDay: 1,
    status: "expired",
    createdAt: "2024-03-25T11:00:00Z",
  },
];
