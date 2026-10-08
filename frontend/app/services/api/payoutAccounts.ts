import type { PayoutAccount } from "~/types/payout";
import type { PayoutAccountsService } from "~/services/contracts/payoutAccounts";

export const apiPayoutAccounts: PayoutAccountsService = {
  list: () => useApi().request<PayoutAccount[]>("/payout-accounts"),

  create: (input) =>
    useApi().request<PayoutAccount>("/payout-accounts", { method: "POST", body: input }),

  update: (id, patch) =>
    useApi().request<PayoutAccount>(`/payout-accounts/${id}`, { method: "PATCH", body: patch }),

  setDefault: (id) =>
    useApi().request<PayoutAccount[]>(`/payout-accounts/${id}/default`, { method: "POST" }),

  remove: async (id) => {
    await useApi().request(`/payout-accounts/${id}`, { method: "DELETE" });
  },
};
