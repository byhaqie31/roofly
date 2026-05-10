/**
 * Per-environment route gate driven by useEnv().
 *
 * - On demo.roofly.my (isDemo): redirect "/" → "/demo" so visitors land on the
 *   curated demo entry. Other routes (/owner/*, /tenant/*, /auth/*) stay
 *   reachable so the body of the demo = the real app rendered with mock data.
 *
 * - On uat.roofly.my and roofly.my (!isDemo): /demo/* returns 404. The demo
 *   landing must never appear in non-demo environments — clients shouldn't see
 *   demo widgets/copy on real prod.
 *
 * Global middleware so it runs for every navigation, including the initial SSR
 * render. Naming `.global.ts` is the Nuxt convention for auto-registration.
 */
export default defineNuxtRouteMiddleware((to) => {
  const { isDemo, redirectRootToDemo } = useEnv();
  const isDemoRoute = to.path === "/demo" || to.path.startsWith("/demo/");

  if (isDemo && to.path === "/" && redirectRootToDemo) {
    return navigateTo("/demo", { redirectCode: 302 });
  }

  if (!isDemo && isDemoRoute) {
    throw createError({
      statusCode: 404,
      statusMessage: "Page not found",
    });
  }
});
