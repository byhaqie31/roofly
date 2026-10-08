import { computed } from "vue";
import { DEMO_TENANT_ID } from "~/demo/auth";

/**
 * Resolves the *current* tenant's record id — the single binding point
 * between the signed-in tenant user and the tenant-scoped data layer.
 *
 * Demo: bound to the seeded tenant the demo login signs in as (Aminah), so
 * every tenant surface has realistic data. API: a real tenant's auth-user id
 * *is* their tenant id.
 */
export const useTenantSession = () => {
  const { useMock } = useEnv();
  const auth = useAuthStore();

  const tenantId = computed<string | null>(() =>
    useMock ? DEMO_TENANT_ID : (auth.user?.id ?? null),
  );

  return { tenantId };
};
