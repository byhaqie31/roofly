import type { PropertiesService } from "~/services/contracts/properties";
import { demoProperties } from "~/demo/services/properties";
import { apiProperties } from "~/services/api/properties";

/** Demo → in-memory seed data; otherwise the Laravel API. Chosen once per call. */
export const useProperties = (): PropertiesService =>
  useEnv().useMock ? demoProperties : apiProperties;
