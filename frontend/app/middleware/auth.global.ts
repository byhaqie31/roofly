/**
 * Auth + role gate for the three app shells.
 *
 * `/owner/*`, `/tenant/*` and `/admin/*` are protected — marketing, `/auth/*`,
 * `/demo/*`, `/coming-soon`, `/suspended` and the two public admin pages
 * (`/admin/login`, `/admin/accept-invite`) keep their own routing. Both this
 * middleware and env.global.ts handle `/admin/*`, and this one runs first
 * (alphabetical order). With `features.admin` false, `/admin/login` is in
 * ADMIN_PUBLIC so it skips this middleware entirely — but env.global.ts still
 * 404s it. A redirect to `/admin/login` (below) re-runs the whole chain and
 * hits that same 404 in env.global.ts.
 *
 * On a hard refresh of a protected page the boot plugin's `fetchMe()` may
 * still be in flight; we await `authReady` so we never bounce a logged-in
 * user to a login page mid-hydration. On the server pass (SSR) `authReady`
 * is false and there's no session cookie access, so we skip — the client
 * plugin + this same guard re-run on hydration.
 */
const ADMIN_PUBLIC = new Set(["/admin/login", "/admin/accept-invite"]);

const shellRootFor = (auth: ReturnType<typeof useAuthStore>) =>
  auth.isAdmin ? "/admin" : auth.isTenant ? "/tenant" : "/owner";

export default defineNuxtRouteMiddleware(async (to) => {
  const isOwnerArea = to.path === "/owner" || to.path.startsWith("/owner/");
  const isTenantArea = to.path === "/tenant" || to.path.startsWith("/tenant/");
  const isAdminArea = (to.path === "/admin" || to.path.startsWith("/admin/")) && !ADMIN_PUBLIC.has(to.path);
  if (!isOwnerArea && !isTenantArea && !isAdminArea) return;

  // SSR has no session cookie here; let the client guard decide post-hydration.
  if (import.meta.server) return;

  const auth = useAuthStore();
  // Wait out the boot hydration if it hasn't settled yet.
  if (!auth.authReady) {
    await auth.fetchMe();
  }

  if (!auth.isAuthenticated) {
    // Admins never see the customer login and vice-versa (spec § 3).
    return navigateTo(isAdminArea ? "/admin/login" : "/auth/login");
  }

  const inWrongShell =
    (isOwnerArea && !auth.isOwner) ||
    (isTenantArea && !auth.isTenant) ||
    (isAdminArea && !auth.isAdmin);
  if (inWrongShell) {
    return navigateTo(shellRootFor(auth));
  }
});
