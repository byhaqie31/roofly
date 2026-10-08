import type { MalaysianBank } from "~/types/payout";

/**
 * Bank names are proper nouns — not translated. Order = the select's order
 * (largest retail banks first, digital banks after, "Other" last). The
 * backend validates the same slugs (`App\Enums\MalaysianBank`) — change both.
 */
export const BANKS: readonly { value: MalaysianBank; label: string }[] = [
  { value: "maybank", label: "Maybank" },
  { value: "cimb", label: "CIMB Bank" },
  { value: "public_bank", label: "Public Bank" },
  { value: "rhb", label: "RHB Bank" },
  { value: "hong_leong", label: "Hong Leong Bank" },
  { value: "ambank", label: "AmBank" },
  { value: "bank_islam", label: "Bank Islam" },
  { value: "bank_rakyat", label: "Bank Rakyat" },
  { value: "bsn", label: "BSN" },
  { value: "affin", label: "Affin Bank" },
  { value: "alliance", label: "Alliance Bank" },
  { value: "ocbc", label: "OCBC Bank" },
  { value: "uob", label: "UOB" },
  { value: "hsbc", label: "HSBC" },
  { value: "standard_chartered", label: "Standard Chartered" },
  { value: "muamalat", label: "Bank Muamalat" },
  { value: "agrobank", label: "Agrobank" },
  { value: "gxbank", label: "GXBank" },
  { value: "aeon_bank", label: "AEON Bank" },
  { value: "boost_bank", label: "Boost Bank" },
  { value: "other", label: "Other" },
];

export const bankLabel = (bank: MalaysianBank): string =>
  BANKS.find((b) => b.value === bank)?.label ?? bank;
