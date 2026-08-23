// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: "2025-07-15",
  devtools: { enabled: true },

  // Admin is client-rendered. The session is restored by a client-only plugin, so an SSR
  // pass has no user and every admin page would server-render <NoAccess>'s Card — Vue then
  // hydrates the real page *into* that Card's <div> (same tag, mismatched classes), leaving
  // the whole page wrapped in a stray raised panel. There is no anonymous SSR value here.
  routeRules: {
    "/admin/**": { ssr: false },
  },

  modules: [
    "@nuxtjs/tailwindcss",
    "@pinia/nuxt",
    "@nuxtjs/i18n",
    "@vee-validate/nuxt",
  ],

  imports: {
    dirs: ["services"],
  },

  css: ["~/assets/css/main.css"],

  tailwindcss: {
    // We supply our own @tailwind directives via main.css; skip the module's default file
    cssPath: false,
  },

  app: {
    head: {
      titleTemplate: "%s · Roofly.my",
      title: "Roofly.my",
      htmlAttrs: { lang: "en" },
      meta: [
        { charset: "utf-8" },
        { name: "viewport", content: "width=device-width, initial-scale=1" },
        { name: "description", content: "Rent management, simplified." },
        // Tints mobile browser chrome (Safari iOS tab, Android address bar).
        // Matches site.webmanifest theme_color.
        { name: "theme-color", content: "#ffffff" },
      ],
      link: [
        // Modern browsers — sharpest at any size, scales as a vector.
        { rel: "icon", type: "image/svg+xml", href: "/favicon/favicon.svg" },
        // Fallback for browsers that don't render SVG favicons.
        { rel: "icon", type: "image/x-icon", href: "/favicon/favicon.ico" },
        // Legacy PNG fallback for older browsers / bookmarks.
        { rel: "icon", type: "image/png", sizes: "96x96", href: "/favicon/favicon-96x96.png" },
        // iOS home-screen icon (apple-touch-icon is 180x180).
        { rel: "apple-touch-icon", href: "/favicon/apple-touch-icon.png" },
        // PWA manifest — covers Android home-screen + Chrome install prompt.
        { rel: "manifest", href: "/favicon/site.webmanifest" },
      ],
    },
  },

  i18n: {
    strategy: "no_prefix",
    vueI18n: "i18n.config.ts",
    defaultLocale: "en",
    locales: [
      { code: "en", name: "English", file: "en.json" },
      { code: "ms", name: "Bahasa Melayu", file: "ms.json" },
    ],
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: "roofly_locale",
      redirectOn: "root",
    },
  },

  veeValidate: {
    autoImports: true,
    componentNames: {
      Form: "VForm",
      Field: "VField",
      FieldArray: "VFieldArray",
      ErrorMessage: "VErrorMessage",
    },
  },

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE ?? "http://localhost:8000/api",
      // Single source of truth for mock-vs-API toggle. Read inside composables
      // via `useEnv().useMock` (which derives from appEnv). Flip per-environment
      // via NUXT_PUBLIC_USE_MOCK=false once each backend endpoint lands.
      useMock: process.env.NUXT_PUBLIC_USE_MOCK !== "false",
      features: {
        // Documents tabs render a "coming in Phase 4" placeholder by default —
        // owners see during demos that file uploads are on the way. Set
        // NUXT_PUBLIC_FEATURE_DOCUMENTS=false to hide the placeholder if needed.
        documents: process.env.NUXT_PUBLIC_FEATURE_DOCUMENTS !== "false",
        // Admin back office (spec 2026-08-23). Default on for uat/prod; useEnv()
        // forces it off in demo regardless of this value.
        admin: process.env.NUXT_PUBLIC_FEATURE_ADMIN !== "false",
      },
      // App environment identifier — drives all UI feature flags via useEnv().
      // Values: "demo" | "uat" | "production". Defaults to production when unset.
      appEnv: process.env.NUXT_PUBLIC_APP_ENV ?? "production",
      // Demo subdomain's floating feedback widget opens this URL in a new tab.
      // Set per-env in docker-compose; empty string hides the widget.
      demoFeedbackUrl: process.env.NUXT_PUBLIC_DEMO_FEEDBACK_URL ?? "",
      // Web3Forms access key for the coming-soon waitlist email capture.
      // Empty = falls back to mock submit (console log only). Get a key at
      // https://web3forms.com — free tier covers 250 submissions/month.
      waitlistAccessKey: process.env.NUXT_PUBLIC_WAITLIST_ACCESS_KEY ?? "",
      // Marketing/analytics tracking (spec 2026-08-23). Default on for
      // uat/prod; useEnv() forces it off in demo (useMock) regardless.
      tracking: process.env.NUXT_PUBLIC_TRACKING !== "false",
    },
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },

  vite: {
    server: {
      hmr: {
        // For containerized dev: HMR through host-exposed port
        clientPort: 3000,
      },
    },
  },
});
