import type { WaitlistService } from "~/services/contracts/waitlist";

/**
 * POST /waitlist — first-party, guest, CSRF-exempt like /track. The visitor id
 * is attached only when tracking is on so the lead's timeline gets a
 * `waitlist_signup` event; with tracking off the backend stores the lead alone.
 */
export const apiWaitlist: WaitlistService = {
  async join({ email, website }) {
    const { trackingEnabled } = useEnv();
    await useApi().request("/waitlist", {
      method: "POST",
      body: {
        email,
        website: website ?? "",
        visitorId: trackingEnabled ? useTrack().visitorId() : undefined,
      },
    });
  },
};
