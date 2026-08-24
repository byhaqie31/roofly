/**
 * Shared Google sign-in handler for the customer login and register pages.
 * Performs the credential exchange via `useAuthStore().loginWithGoogle` and
 * maps the two failure modes the backend can return — 403 `not_owner`, or
 * anything else (bad/expired token, network error) — to the right i18n
 * string. Each page supplies its own `onSuccess` for post-login behaviour:
 * login navigates by role, register additionally fires the `register`
 * tracking beacon for a genuinely new account before navigating.
 */
export const useGoogleSignIn = (onSuccess: () => void | Promise<void>) => {
  const auth = useAuthStore();
  const { t } = useI18n();
  const { track } = useTrack();
  const googleError = ref<string | null>(null);

  const onGoogle = async (credential: string) => {
    googleError.value = null;
    try {
      await auth.loginWithGoogle(credential);
      // Only count a genuinely new Google account as a registration — a
      // returning owner signing in via either page has onboardedAt already
      // set. Shared here so both /auth/login and /auth/register report the
      // same cohort to analytics regardless of which page created the account.
      if (auth.user?.onboardedAt === null) {
        track("register", { email: auth.user?.email ?? "", userId: auth.user?.id ?? "" });
      }
      await onSuccess();
    } catch (err) {
      const code = (err as { data?: { code?: string } })?.data?.code;
      googleError.value = code === "not_owner" ? t("auth.google.notOwner") : t("auth.google.failed");
    }
  };

  return { googleError, onGoogle };
};
