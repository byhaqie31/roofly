import type { SupportService } from "~/services/contracts/support";

/** POST /support/enquiries — owner or tenant session; the backend copies name/email/role from it. */
export const apiSupport: SupportService = {
  async send({ type, message, pageUrl, pageLabel }) {
    await useApi().request("/support/enquiries", { method: "POST", body: { type, message, pageUrl, pageLabel } });
  },
};
