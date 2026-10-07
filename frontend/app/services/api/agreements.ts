import type { Agreement } from "~/types/agreement";
import type {
  AgreementsService,
  AgreementWithRefs,
} from "~/services/contracts/agreements";

export const apiAgreements: AgreementsService = {
  getAgreements: () => useApi().request<Agreement[]>("/agreements"),

  getAgreementsWithRefs: () =>
    useApi().request<AgreementWithRefs[]>(
      "/agreements?expand=unit,property,tenant",
    ),

  getAgreement: (id) => useApi().request<Agreement>(`/agreements/${id}`),

  getActiveAgreementForTenant: () =>
    useApi().request<AgreementWithRefs | null>(
      "/me/agreement?expand=unit,property,tenant",
    ),

  create: (input) =>
    useApi().request<Agreement>("/agreements", { method: "POST", body: input }),

  update: (id, patch) =>
    useApi().request<Agreement>(`/agreements/${id}`, {
      method: "PATCH",
      body: patch,
    }),

  remove: async (id) => {
    await useApi().request(`/agreements/${id}`, { method: "DELETE" });
  },

  send: (id) => useApi().request<Agreement>(`/agreements/${id}/send`, { method: "POST" }),

  withdraw: (id) => useApi().request<Agreement>(`/agreements/${id}/withdraw`, { method: "POST" }),

  acceptForTenant: (_tenantId, agreementId) =>
    useApi().request<Agreement>(`/me/agreements/${agreementId}/accept`, { method: "POST" }),

  requestChangesForTenant: (_tenantId, agreementId, note) =>
    useApi().request<Agreement>(`/me/agreements/${agreementId}/request-changes`, {
      method: "POST",
      body: { note },
    }),
};
