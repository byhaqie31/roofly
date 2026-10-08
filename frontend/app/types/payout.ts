/**
 * Owner payout accounts — where tenants send rent by DuitNow / bank transfer
 * today, and where a payment gateway will settle later.
 * Spec: docs/superpowers/specs/2026-10-08-payout-accounts-duitnow-design.md
 */

/** Slugs validated by the backend (`App\Enums\MalaysianBank`); labels in `config/banks.ts`. */
export type MalaysianBank =
  | "maybank"
  | "cimb"
  | "public_bank"
  | "rhb"
  | "hong_leong"
  | "ambank"
  | "bank_islam"
  | "bank_rakyat"
  | "bsn"
  | "affin"
  | "alliance"
  | "ocbc"
  | "uob"
  | "hsbc"
  | "standard_chartered"
  | "muamalat"
  | "agrobank"
  | "gxbank"
  | "aeon_bank"
  | "boost_bank"
  | "other";

export type DuitNowIdType = "phone" | "mykad" | "brn" | "passport";

export interface PayoutAccount {
  id: string;
  label: string;
  bank: MalaysianBank;
  accountHolderName: string;
  accountNumber: string | null;   // digits only
  duitnowIdType: DuitNowIdType | null;
  duitnowId: string | null;
  isDefault: boolean;             // exactly one per owner
  agreementCount: number;         // agreements pointing at it explicitly (owner endpoints; 0 elsewhere)
  createdAt: string;
}

export interface PayoutAccountInput {
  label: string;
  bank: MalaysianBank;
  accountHolderName: string;
  accountNumber: string | null;
  duitnowIdType: DuitNowIdType | null;
  duitnowId: string | null;
  isDefault?: boolean;
}

export type PayoutAccountUpdate = Partial<PayoutAccountInput>;
