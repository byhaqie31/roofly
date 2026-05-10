/**
 * Hydrate the auth store from localStorage on first client load so a
 * page refresh doesn't drop the session. Pinia state is in-memory only;
 * without this, every refresh resets `user` to null.
 *
 * `.client.ts` keeps it out of the SSR pass — server has no localStorage.
 * When Sanctum lands, swap the body for `await auth.fetchMe()`.
 */
export default defineNuxtPlugin(() => {
  const auth = useAuthStore();
  auth.restoreSession();
});
