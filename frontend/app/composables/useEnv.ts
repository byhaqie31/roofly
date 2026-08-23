/**
 * Central derivation of per-environment behaviour.
 *
 * One env var (`NUXT_PUBLIC_APP_ENV`) → many semantic flags. Components and
 * services ask for the behaviour they need ("am I in demo?", "should the
 * feedback widget show?"), not for the raw env name. Adding a new env-driven
 * feature = one new derived flag here, no scattering env checks across files.
 *
 * Defaults to "production" if NUXT_PUBLIC_APP_ENV is unset, so prod is the
 * safe failure mode (no demo widgets, no UAT banners leaking).
 */

export type AppEnv = "demo" | "uat" | "production";

const KNOWN_ENVS: readonly AppEnv[] = ["demo", "uat", "production"] as const;

export const useEnv = () => {
  const config = useRuntimeConfig();
  const raw = config.public.appEnv as string;

  // Unknown values fall back to "production" — same safe default as unset.
  const env: AppEnv = (KNOWN_ENVS as readonly string[]).includes(raw)
    ? (raw as AppEnv)
    : "production";

  const isDemo = env === "demo";
  const isUat = env === "uat";
  const isProduction = env === "production";

  // Hostname rule, not a separate app: admin.roofly.my serves the same build
  // and env.global.ts sends "/" to "/admin" there. SSR-safe via useRequestURL.
  const hostname = useRequestURL().hostname;
  const isAdminHost = hostname === "admin.roofly.my" || hostname.startsWith("admin.");

  return {
    env,
    isDemo,
    isUat,
    isProduction,
    isAdminHost,

    // Data layer — demo uses curated mocks forever; uat/prod follow the
    // service-level useMock flag (which itself flips off per-endpoint as the
    // backend lands). isDemo wins over the runtime flag so demo always sees
    // its mock data even if NUXT_PUBLIC_USE_MOCK is left at "false".
    useMock: isDemo || config.public.useMock,

    // Feature flags. `admin` is never on in demo — demo-roofly must not show
    // the back office (spec § 2).
    features: {
      documents: config.public.features.documents,
      admin: !isDemo && config.public.features.admin,
    },

    // UI feature flags
    showDemoShortcuts: isDemo,
    showFloatingFeedback: isDemo && Boolean(config.public.demoFeedbackUrl),
    showEnvBanner: isUat,
    redirectRootToDemo: isDemo,
  };
};
