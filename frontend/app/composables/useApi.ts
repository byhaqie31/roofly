/**
 * Sanctum-aware $fetch wrapper.
 * Phase 1: backend isn't wired yet, so this is a thin shim returning
 * the configured apiBase. Real Sanctum cookie handling lands when the
 * Laravel install commit goes in.
 */
export const useApi = () => {
  const config = useRuntimeConfig();
  const baseURL = config.public.apiBase;

  const request = $fetch.create({
    baseURL,
    credentials: "include",
    headers: { Accept: "application/json" },
  });

  return { request, baseURL };
};
