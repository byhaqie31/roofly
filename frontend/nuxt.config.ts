// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: "2025-07-15",
  devtools: { enabled: true },

  modules: [
    "@nuxtjs/tailwindcss",
    "@pinia/nuxt",
    "@nuxtjs/i18n",
    "@vee-validate/nuxt",
  ],

  imports: {
    dirs: ["services"],
  },

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
      ],
      link: [
        { rel: "icon", type: "image/png", href: "/home.png" },
        { rel: "apple-touch-icon", href: "/home.png" },
      ],
      link: [
        { rel: "icon", type: "image/png", href: "/home.png" },
        { rel: "apple-touch-icon", href: "/home.png" },
      ],
    },
  },

  i18n: {
    strategy: "no_prefix",
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
      // Single source of truth for mock-vs-API toggle. Phase 2 default: true.
      // Flip per-environment via NUXT_PUBLIC_USE_MOCK=false once each backend endpoint lands.
      useMock: process.env.NUXT_PUBLIC_USE_MOCK !== "false",
      features: {
        // Documents tabs render a "coming in Phase 4" placeholder by default —
        // owners see during demos that file uploads are on the way. Set
        // NUXT_PUBLIC_FEATURE_DOCUMENTS=false to hide the placeholder if needed.
        documents: process.env.NUXT_PUBLIC_FEATURE_DOCUMENTS !== "false",
      },
      // Demo deploy (demo.roofly.my) sets NUXT_PUBLIC_DEMO_MODE=true so the
      // owner/tenant quick-login shortcuts on the login page are visible to clients.
      // Local dev still shows them via import.meta.dev.
      demoMode: process.env.NUXT_PUBLIC_DEMO_MODE === "true",
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
