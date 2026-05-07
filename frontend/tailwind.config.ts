import type { Config } from "tailwindcss";

/**
 * Tailwind config — mirrors design tokens from assets/css/tokens.css.
 * Per UI-Standards.md, tokens are the source of truth; Tailwind utilities
 * reference CSS variables so dark mode swaps automatically.
 */
export default {
  darkMode: ["class", '[data-theme="dark"]'],
  content: [
    "./app/**/*.{vue,js,ts}",
    "./components/**/*.{vue,js,ts}",
    "./layouts/**/*.vue",
    "./pages/**/*.vue",
    "./plugins/**/*.{js,ts}",
    "./composables/**/*.ts",
    "./app.vue",
    "./error.vue",
  ],
  theme: {
    extend: {
      colors: {
        surface: {
          page: "var(--surface-page)",
          dark: "var(--surface-button-dark)",
          ondark: "var(--surface-on-dark)",
        },
        ink: {
          DEFAULT: "var(--text-primary)",
          strong: "var(--text-strong)",
          body: "var(--text-body)",
          muted: "var(--text-muted)",
          faint: "var(--text-faint)",
        },
        line: {
          passive: "var(--border-passive)",
          interactive: "var(--border-interactive)",
          hover: "var(--border-hover)",
        },
        accent: {
          DEFAULT: "var(--accent)",
          hover: "var(--accent-hover)",
          soft: "var(--accent-soft)",
        },
        status: {
          vacant: "var(--status-vacant)",
          "vacant-soft": "var(--status-vacant-soft)",
          occupied: "var(--status-occupied)",
          "occupied-soft": "var(--status-occupied-soft)",
          maintenance: "var(--status-maintenance)",
          "maintenance-soft": "var(--status-maintenance-soft)",
          paid: "var(--status-paid)",
          "paid-soft": "var(--status-paid-soft)",
          pending: "var(--status-pending)",
          "pending-soft": "var(--status-pending-soft)",
          overdue: "var(--status-overdue)",
          "overdue-soft": "var(--status-overdue-soft)",
          cancelled: "var(--status-cancelled)",
          "cancelled-soft": "var(--status-cancelled-soft)",
          draft: "var(--status-draft)",
          "draft-soft": "var(--status-draft-soft)",
          active: "var(--status-active)",
          "active-soft": "var(--status-active-soft)",
          expired: "var(--status-expired)",
          "expired-soft": "var(--status-expired-soft)",
          terminated: "var(--status-terminated)",
          "terminated-soft": "var(--status-terminated-soft)",
        },
        priority: {
          low: "var(--priority-low)",
          "low-soft": "var(--priority-low-soft)",
          medium: "var(--priority-medium)",
          "medium-soft": "var(--priority-medium-soft)",
          high: "var(--priority-high)",
          "high-soft": "var(--priority-high-soft)",
        },
      },
      borderRadius: {
        xs: "var(--radius-xs)",
        sm: "var(--radius-sm)",
        md: "var(--radius-md)",
        lg: "var(--radius-lg)",
        xl: "var(--radius-xl)",
        pill: "var(--radius-pill)",
      },
      boxShadow: {
        "btn-inset": "var(--shadow-button-inset)",
        focus: "var(--shadow-focus)",
        modal: "var(--shadow-modal)",
      },
      fontFamily: {
        sans: ['"Inter Variable"', "ui-sans-serif", "system-ui", "sans-serif"],
      },
      fontSize: {
        // Display scale per UI-Standards.md § 2.3
        "display-hero": ["3.75rem", { lineHeight: "1.05", letterSpacing: "-1.5px", fontWeight: "600" }],
        "display-section": ["3rem", { lineHeight: "1.0", letterSpacing: "-1.2px", fontWeight: "600" }],
        "display-sub": ["2.25rem", { lineHeight: "1.1", letterSpacing: "-0.9px", fontWeight: "600" }],
        "card-title": ["1.25rem", { lineHeight: "1.25", fontWeight: "400" }],
        "body-lg": ["1.125rem", { lineHeight: "1.38" }],
        body: ["1rem", { lineHeight: "1.5" }],
        caption: ["0.875rem", { lineHeight: "1.5" }],
        micro: ["0.75rem", { lineHeight: "1.4" }],
      },
      letterSpacing: {
        tighter: "-1.5px",
        tight: "-1.2px",
        snug: "-0.9px",
      },
      maxWidth: {
        "auth-card": "420px",
        "form-readable": "840px",
        "marketing": "1200px",
        "app": "1280px",
      },
    },
  },
  plugins: [],
} satisfies Config;
