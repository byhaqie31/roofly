type Theme = "light" | "dark" | "system";

const COOKIE_KEY = "roofly_theme";
const LEGACY_STORAGE_KEY = "roofly_theme";

export const useTheme = () => {
  const theme = useCookie<Theme>(COOKIE_KEY, {
    default: () => "system",
    sameSite: "lax",
    maxAge: 60 * 60 * 24 * 365,
  });

  const systemDark = useState<boolean | null>("roofly-system-dark", () => null);

  const resolved = computed<"light" | "dark" | undefined>(() => {
    if (theme.value === "light") return "light";
    if (theme.value === "dark") return "dark";
    if (systemDark.value === null) return undefined;
    return systemDark.value ? "dark" : "light";
  });

  useHead({
    htmlAttrs: {
      "data-theme": resolved as unknown as string,
    },
  });

  const setTheme = (next: Theme) => {
    theme.value = next;
  };

  const initTheme = () => {
    if (!import.meta.client) return;

    const legacy = localStorage.getItem(LEGACY_STORAGE_KEY) as Theme | null;
    if (legacy && legacy !== theme.value) {
      theme.value = legacy;
      localStorage.removeItem(LEGACY_STORAGE_KEY);
    }

    const mq = window.matchMedia("(prefers-color-scheme: dark)");
    systemDark.value = mq.matches;
    mq.addEventListener("change", (e) => {
      systemDark.value = e.matches;
    });
  };

  return { theme, setTheme, initTheme };
};
