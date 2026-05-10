/**
 * Per-environment route gate driven by useEnv().
 *
 * Routing matrix:
 *
 * Path        | demo subdomain          | uat / prod (unauthenticated) | uat / prod (authenticated)
 * ------------|-------------------------|------------------------------|---------------------------
 * /           | redirect → /demo        | redirect → /coming-soon       | falls through (auth-based)
 * /demo/*     | render                  | 404                          | 404
 * /coming-soon| redirect → /demo        | render                       | render
 * everything  | render                  | render                       | render
 *
 * Why:
 *  - Demo subdomain: clients land directly on the curated demo, never see the
 *    pre-launch marketing page.
 *  - uat/prod: pre-launch state — root URL shows the marketing/coming-soon page.
 *    Once the product launches, swap this to redirect to /auth/login (or just
 *    delete this branch and let pages/index.vue handle auth-based routing).
 *  - Authenticated users on uat/prod skip /coming-soon — they're either testers
 *    or real customers and should land in their dashboard via pages/index.vue.
 */
export default defineNuxtRouteMiddleware((to) => {
  const { isDemo } = useEnv();
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
  if (isDemoRoute) {
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
