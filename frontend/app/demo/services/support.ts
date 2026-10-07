import type { SupportService } from "~/services/contracts/support";
import { enquiriesMock } from "~/demo/data/enquiries";

/** Mock mode: lands in the in-memory inbox the admin demo adapter reads. (Demo itself hides the button.) */
export const demoSupport: SupportService = {
  async send(input) {
    await new Promise((resolve) => setTimeout(resolve, 400));
    const user = useAuthStore().user;
    enquiriesMock.unshift({
      id: `enq-${Date.now()}`,
      type: input.type,
      status: "new",
      message: input.message.trim(),
      pageUrl: input.pageUrl ?? null,
      name: user?.name ?? "Demo user",
      email: user?.email ?? "demo@roofly.my",
      role: user?.role === "tenant" ? "tenant" : "owner",
      userId: user?.id ?? null,
      adminNote: null,
      handledByName: null,
      statusChangedAt: null,
      createdAt: new Date().toISOString(),
    });
  },
};
