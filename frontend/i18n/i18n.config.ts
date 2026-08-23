/**
 * vue-i18n runtime options (loaded by @nuxtjs/i18n v9 from the i18n/ dir).
 *
 * fallbackLocale = "en": the admin shell is English-only (`admin.*` and
 * `auth.admin.*` exist in en.json only). Without a fallback, a BM-locale
 * user hitting /admin would see raw key paths on the SSR pass before the
 * admin layout pins the locale to "en" on mount.
 */
export default defineI18nConfig(() => ({
  legacy: false,
  fallbackLocale: "en",
  // Missing-key warnings are noise in dev once fallback is intentional.
  missingWarn: false,
  fallbackWarn: false,
}));
