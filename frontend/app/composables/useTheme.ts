type Theme = "light" | "dark" | "system";

const STORAGE_KEY = "roofly_theme";

export const useTheme = () => {
  const theme = useState<Theme>("roofly-theme", () => "system");

  const applyTheme = (next: Theme) => {
    if (!import.meta.client) return;
    const resolved =
      next === "system"
        ? window.matchMedia("(prefers-color-scheme: dark)").matches
          ? "dark"
          : "light"
        : next;
    document.documentElement.dataset.theme = resolved;
  };

  const setTheme = (next: Theme) => {
    theme.value = next;
    if (import.meta.client) {
      localStorage.setItem(STORAGE_KEY, next);
      applyTheme(next);
    }
  };

  const initTheme = () => {
    if (!import.meta.client) return;
    const stored = (localStorage.getItem(STORAGE_KEY) as Theme | null) ?? "system";
    theme.value = stored;
    applyTheme(stored);

    if (stored === "system") {
      window
        .matchMedia("(prefers-color-scheme: dark)")
        .addEventListener("change", () => applyTheme("system"));
    }
  };

  return { theme, setTheme, initTheme };
};
