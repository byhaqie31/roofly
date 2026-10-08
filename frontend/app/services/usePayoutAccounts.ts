import type { PayoutAccountsService } from "~/services/contracts/payoutAccounts";
import { demoPayoutAccounts } from "~/demo/services/payoutAccounts";
import { apiPayoutAccounts } from "~/services/api/payoutAccounts";

/** Demo → in-memory seed data; otherwise the Laravel API. Chosen once per call. */
export const usePayoutAccounts = (): PayoutAccountsService =>
  useEnv().useMock ? demoPayoutAccounts : apiPayoutAccounts;
