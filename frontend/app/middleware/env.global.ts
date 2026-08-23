/**
 * Per-environment route gate driven by useEnv().
 *
 * Routing matrix:
 *
 * Path          | demo subdomain          | uat (unauth)              | uat (auth)                    | production (unauth) | production (auth)
 * --------------|-------------------------|---------------------------|-------------------------------|---------------------|---------------------
 * /             | redirect → /demo        | redirect → /coming-soon   | falls through (auth-based)    | redirect → /coming-soon | falls through
 * /demo/*       | render                  | render (testers preview)  | render                        | 404                 | 404
 * /coming-soon  | redirect → /demo        | render                    | render                        | render              | render
 * /admin/*      | 404 (features.admin off)| render when features.admin (else 404) | render when features.admin (else 404) | render when features.admin (else 404) | render when features.admin (else 404)
 * / on admin host | n/a (demo has no admin host) | redirect → /admin    | redirect → /admin              | redirect → /admin   | redirect → /admin
 * everything    | render                  | render                    | render                        | render              | render
 *
 * Why:
 *  - Demo subdomain: clients land directly on the curated demo, never see the
 *    pre-launch marketing page.
 *  - UAT: testers / stakeholders can preview /demo by URL, but root still shows
 *    the marketing page so it behaves like prod for the unauth flow.
 *  - Production: /demo is hidden so real customers never stumble onto it.
 *  - Authenticated users on uat/prod skip /coming-soon — they're either testers
 *    or real customers and should land in their dashboard via pages/index.vue.
 *  - Admin back office is a feature flag (forced off in demo, spec § 2) — the
 *    check runs first, ahead of every other branch, so a disabled `/admin/*`
 *    404s regardless of env or auth state. Both this middleware and
 *    auth.global.ts handle `/admin/*`; auth.global.ts runs first
 *    (alphabetical). With `features.admin` false, `/admin/login` skips
 *    auth.global.ts (it's a public admin path there) but still lands here and
 *    404s. Redirecting to `/admin/login` (e.g. from auth.global.ts) re-runs
 *    the whole middleware chain and hits this same 404 again.
 *  - admin.roofly.my is a hostname rule, not a separate app: root on that host
 *    always redirects straight to `/admin`, ahead of the demo/uat/prod split.
 */
export default defineNuxtRouteMiddleware((to) => {
  const { isDemo, isProduction, isAdminHost, features } = useEnv();
  const isDemoRoute = to.path === "/demo" || to.path.startsWith("/demo/");
  const isComingSoon = to.path === "/coming-soon";
  const isAdminRoute = to.path === "/admin" || to.path.startsWith("/admin/");

  // Admin back office is a feature flag — forced off in demo (spec § 2/3).
  if (isAdminRoute && !features.admin) {
    throw createError({ statusCode: 404, statusMessage: "Page not found" });
  }

  // admin.roofly.my — root goes straight to the back office.
  if (isAdminHost && to.path === "/") {
    return navigateTo("/admin", { redirectCode: 302 });
  }

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
