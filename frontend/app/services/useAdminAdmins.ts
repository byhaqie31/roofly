import type { AdminAdminsService } from "~/services/contracts/admin/admins";
import { demoAdminAdmins } from "~/demo/services/admin/admins";
import { apiAdminAdmins } from "~/services/api/admin/admins";

export const useAdminAdmins = (): AdminAdminsService =>
  useEnv().useMock ? demoAdminAdmins : apiAdminAdmins;
