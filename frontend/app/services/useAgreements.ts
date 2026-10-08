import type { AgreementsService } from "~/services/contracts/agreements";
import { demoAgreements } from "~/demo/services/agreements";
import { apiAgreements } from "~/services/api/agreements";

export type { AgreementWithRefs } from "~/services/contracts/agreements";

/** Demo → in-memory seed data; otherwise the Laravel API. Chosen once per call. */
export const useAgreements = (): AgreementsService =>
  useEnv().useMock ? demoAgreements : apiAgreements;
