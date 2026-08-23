import { computed } from "vue";
import type { AdminPermission } from "~/types/admin";

/**
 * UI-side permission check (spec § 5). Hides / disables controls only —
 * the API is the enforcement. Super-admins pass everything.
 */
export const useAdminPermissions = () => {
  const auth = useAuthStore();

  const can = (key: AdminPermission): boolean => {
    const u = auth.user;
    if (!u || u.role !== "admin") return false;
    return u.isSuperAdmin || u.permissions.includes(key);
  };

  const isSuperAdmin = computed(() => auth.user?.role === "admin" && auth.user.isSuperAdmin);

  return { can, isSuperAdmin };
};
