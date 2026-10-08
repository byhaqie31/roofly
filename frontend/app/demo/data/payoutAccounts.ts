import type { PayoutAccount } from "~/types/payout";

/**
 * Aminah's payout accounts — mirrors `DemoSeeder`. `agreementCount` is
 * computed by the demo adapter from `agreementsMock`, never stored here.
 */
export const payoutAccountsMock: PayoutAccount[] = [
  {
    id: "pa-maybank",
    label: "Personal Maybank",
    bank: "maybank",
    accountHolderName: "Cik Aminah",
    accountNumber: "514012344521",
    duitnowIdType: "phone",
    duitnowId: "+60123456789",
    isDefault: true,
    agreementCount: 0,
    createdAt: "2025-08-20T09:00:00Z",
  },
  {
    id: "pa-cimb",
    label: "Aminah Properties CIMB",
    bank: "cimb",
    accountHolderName: "Aminah Properties",
    accountNumber: "8604123456",
    duitnowIdType: "brn",
    duitnowId: "202301012345",
    isDefault: false,
    agreementCount: 0,
    createdAt: "2025-11-15T09:00:00Z",
  },
];
