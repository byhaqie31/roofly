import type { WaitlistService } from "~/services/contracts/waitlist";

/** Demo never stores or sends anything — a short pause so the form's loading state is visible. */
export const demoWaitlist: WaitlistService = {
  async join() {
    await new Promise((resolve) => setTimeout(resolve, 400));
  },
};
