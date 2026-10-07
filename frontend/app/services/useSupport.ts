import type { SupportService } from "~/services/contracts/support";
import { demoSupport } from "~/demo/services/support";
import { apiSupport } from "~/services/api/support";

export type { SupportService, SupportEnquiryInput } from "~/services/contracts/support";

export const useSupport = (): SupportService => (useEnv().useMock ? demoSupport : apiSupport);
