import type { UnitsService } from "~/services/contracts/units";
import { demoUnits } from "~/demo/services/units";
import { apiUnits } from "~/services/api/units";

/** Demo → in-memory seed data; otherwise the Laravel API. Chosen once per call. */
export const useUnits = (): UnitsService =>
  useEnv().useMock ? demoUnits : apiUnits;
