/**
 * Per-environment route gate driven by useEnv().
 *
 * Routing matrix:
 *
 * Path        | demo subdomain          | uat (unauth)              | uat (auth)                    | production (unauth) | production (auth)
 * ------------|-------------------------|---------------------------|-------------------------------|---------------------|---------------------
 * /           | redirect → /demo        | redirect → /coming-soon   | falls through (auth-based)    | redirect → /coming-soon | falls through
 * /demo/*     | render                  | render (testers preview)  | render                        | 404                 | 404
 * /coming-soon| redirect → /demo        | render                    | render                        | render              | render
 * everything  | render                  | render                    | render                        | render              | render
 *
 * Why:
 *  - Demo subdomain: clients land directly on the curated demo, never see the
 *    pre-launch marketing page.
 *  - UAT: testers / stakeholders can preview /demo by URL, but root still shows
 *    the marketing page so it behaves like prod for the unauth flow.
 *  - Production: /demo is hidden so real customers never stumble onto it.
 *  - Authenticated users on uat/prod skip /coming-soon — they're either testers
 *    or real customers and should land in their dashboard via pages/index.vue.
 */
export default defineNuxtRouteMiddleware((to) => {
  const { isDemo, isProduction } = useEnv();
  const isDemoRoute = to.path === "/demo" || to.path.startsWith("/demo/");
  const isComingSoon = to.path === "/coming-soon";

  if (isDemo) {
    // Demo subdomain — /coming-soon doesn't apply here; bounce to /demo
    if (to.path === "/" || isComingSoon) {
      return navigateTo("/demo", { redirectCode: 302 });
    }
    return;
  }

  // uat / prod from here onwards
  if (isDemoRoute && isProduction) {
    throw createError({ statusCode: 404, statusMessage: "Page not found" });
  }

  if (to.path === "/") {
    const auth = useAuthStore();
    if (!auth.isAuthenticated) {
      return navigateTo("/coming-soon", { redirectCode: 302 });
    }
    // Authenticated → falls through to pages/index.vue (role-based redirect)
  }
});
