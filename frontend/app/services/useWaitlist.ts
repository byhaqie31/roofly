import type { WaitlistService } from "~/services/contracts/waitlist";
import { demoWaitlist } from "~/demo/services/waitlist";
import { apiWaitlist } from "~/services/api/waitlist";

export type { WaitlistService, WaitlistSignup } from "~/services/contracts/waitlist";

export const useWaitlist = (): WaitlistService => (useEnv().useMock ? demoWaitlist : apiWaitlist);
