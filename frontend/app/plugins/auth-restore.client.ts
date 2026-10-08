/**
 * Hydrate auth state from the Sanctum session on first client load, so a
 * refresh doesn't drop the user. Replaces the old localStorage restore —
 * the httpOnly session cookie is now the single source of truth.
 *
 * `.client.ts` keeps it off the SSR pass (no cookies/`document` server-side).
 */
export default defineNuxtPlugin(async () => {
  const auth = useAuthStore();
  await auth.fetchMe();
});
