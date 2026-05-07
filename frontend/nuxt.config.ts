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
