import type { PayoutAccount } from "~/types/payout";
import type { PayoutAccountsService } from "~/services/contracts/payoutAccounts";
import { payoutAccountsMock } from "~/demo/data/payoutAccounts";
import { agreementsMock } from "~/demo/data/agreements";

const digits = (v: string | null | undefined) => (v ? v.replace(/\D/g, "") || null : null);

const withCount = (a: PayoutAccount): PayoutAccount => ({
  ...structuredClone(a),
  agreementCount: agreementsMock.filter((ag) => ag.payoutAccountId === a.id).length,
});

/** Default first, then oldest — same order as the API. */
const sorted = () =>
  [...payoutAccountsMock]
    .sort((a, b) => Number(b.isDefault) - Number(a.isDefault) || a.createdAt.localeCompare(b.createdAt))
    .map(withCount);

const makeDefault = (id: string) => {
  payoutAccountsMock.forEach((a) => {
    a.isDefault = a.id === id;
  });
};

/** Same rule as the FormRequest: at least one way to pay. */
const assertPayable = (a: Pick<PayoutAccount, "accountNumber" | "duitnowId">) => {
  if (!a.accountNumber && !a.duitnowId) throw new Error("Account number or DuitNow ID is required");
};

export const demoPayoutAccounts: PayoutAccountsService = {
  async list() {
    return sorted();
  },

  async create(input) {
    const created: PayoutAccount = {
      id: crypto.randomUUID(),
      label: input.label,
      bank: input.bank,
      accountHolderName: input.accountHolderName,
      accountNumber: digits(input.accountNumber),
      duitnowIdType: input.duitnowId ? input.duitnowIdType : null,
      duitnowId: input.duitnowIdType ? input.duitnowId || null : null,
      isDefault: false,
      agreementCount: 0,
      createdAt: new Date().toISOString(),
    };
    assertPayable(created);
    payoutAccountsMock.push(created);
    if (payoutAccountsMock.length === 1 || input.isDefault) makeDefault(created.id);
    return withCount(payoutAccountsMock.find((a) => a.id === created.id)!);
  },

  async update(id, patch) {
    const idx = payoutAccountsMock.findIndex((a) => a.id === id);
    if (idx === -1) throw new Error(`Payout account ${id} not found`);
    const { isDefault, ...fields } = patch;
    const next: PayoutAccount = { ...payoutAccountsMock[idx]!, ...fields };
    if ("accountNumber" in fields) next.accountNumber = digits(fields.accountNumber);
    if (!next.duitnowIdType || !next.duitnowId) {
      next.duitnowIdType = null;
      next.duitnowId = null;
    }
    assertPayable(next);
    payoutAccountsMock[idx] = next;
    if (isDefault) makeDefault(id);
    return withCount(payoutAccountsMock[idx]!);
  },

  async setDefault(id) {
    if (!payoutAccountsMock.some((a) => a.id === id)) throw new Error(`Payout account ${id} not found`);
    makeDefault(id);
    return sorted();
  },

  async remove(id) {
    const idx = payoutAccountsMock.findIndex((a) => a.id === id);
    if (idx === -1) return;
    const [removed] = payoutAccountsMock.splice(idx, 1);
    // FK nullOnDelete: agreements fall back to the default.
    agreementsMock.forEach((ag) => {
      if (ag.payoutAccountId === id) ag.payoutAccountId = null;
    });
    if (removed?.isDefault && payoutAccountsMock.length > 0) {
      const oldest = [...payoutAccountsMock].sort((a, b) => a.createdAt.localeCompare(b.createdAt))[0]!;
      makeDefault(oldest.id);
    }
  },
};

/** Agreement's own account ?? the default ?? null — the demo twin of `Agreement::resolvedPayoutAccount()`. */
export const resolvePayoutAccount = (payoutAccountId: string | null | undefined): PayoutAccount | null => {
  const found =
    (payoutAccountId && payoutAccountsMock.find((a) => a.id === payoutAccountId)) ||
    payoutAccountsMock.find((a) => a.isDefault);
  return found ? { ...structuredClone(found), agreementCount: 0 } : null;
};
