import type {
  PayoutAccount,
  PayoutAccountInput,
  PayoutAccountUpdate,
} from "~/types/payout";

/** Owner-scoped. API: `/payout-accounts` (spec 2026-10-08 § 4). */
export interface PayoutAccountsService {
  /** Default first, then oldest. */
  list(): Promise<PayoutAccount[]>;
  /** The owner's first account becomes the default regardless of `isDefault`. */
  create(input: PayoutAccountInput): Promise<PayoutAccount>;
  update(id: string, patch: PayoutAccountUpdate): Promise<PayoutAccount>;
  /** Returns the refreshed list (exactly one default). */
  setDefault(id: string): Promise<PayoutAccount[]>;
  /** Deleting the default promotes the oldest remaining; agreements pointing here fall back to the default. */
  remove(id: string): Promise<void>;
}
