import type { OwnerSettingsService } from "~/services/contracts/ownerSettings";
import { demoOwnerSettings } from "~/demo/services/ownerSettings";
import { apiOwnerSettings } from "~/services/api/ownerSettings";

/** Demo → in-memory seed data; otherwise the Laravel API. Chosen once per call. */
export const useOwnerSettings = (): OwnerSettingsService =>
  useEnv().useMock ? demoOwnerSettings : apiOwnerSettings;
