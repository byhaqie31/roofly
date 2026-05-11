/**
 * Demo product tour wrapper around driver.js.
 *
 * Owner shell only for the first cut — five steps over the dashboard.
 * Driver.js + its CSS are dynamically imported so they never enter the
 * non-demo bundle (the trigger button and auto-start are gated by `isDemo`
 * upstream). Re-runnable from the topbar; auto-starts once per browser via
 * a localStorage flag.
 */
const STORAGE_KEY = "roofly_tour_owner_seen";

export const useDemoTour = () => {
  const { isDemo } = useEnv();
  const { t } = useI18n();

  const buildSteps = (isMobile: boolean) => {
    const welcome = {
      popover: {
        title: t("demo.tour.steps.welcome.title"),
        description: t("demo.tour.steps.welcome.description"),
      },
    };

    // Sidebar exists only at md+; on mobile the hamburger plays the same role.
    const navStep = isMobile
      ? {
          element: '[data-tour="mobile-menu"]',
          popover: {
            title: t("demo.tour.steps.mobileMenu.title"),
            description: t("demo.tour.steps.mobileMenu.description"),
            side: "bottom" as const,
            align: "start" as const,
          },
        }
      : {
          element: '[data-tour="sidebar"]',
          popover: {
            title: t("demo.tour.steps.sidebar.title"),
            description: t("demo.tour.steps.sidebar.description"),
            side: "right" as const,
            align: "start" as const,
          },
        };

    return [
      welcome,
      navStep,
      {
        element: '[data-tour="stats"]',
        popover: {
          title: t("demo.tour.steps.stats.title"),
          description: t("demo.tour.steps.stats.description"),
          side: "bottom" as const,
        },
      },
      {
        element: '[data-tour="income-chart"]',
        popover: {
          title: t("demo.tour.steps.incomeChart.title"),
          description: t("demo.tour.steps.incomeChart.description"),
          side: "top" as const,
        },
      },
      {
        element: '[data-tour="attention"]',
        popover: {
          title: t("demo.tour.steps.attention.title"),
          description: t("demo.tour.steps.attention.description"),
          side: "top" as const,
        },
      },
      {
        element: '[data-tour="feedback"]',
        popover: {
          title: t("demo.tour.steps.feedback.title"),
          description: t("demo.tour.steps.feedback.description"),
          // Bottom-right floating button: on mobile float the popover above
          // it, on desktop float it to the left so it doesn't cover the chart.
          side: isMobile ? ("top" as const) : ("left" as const),
          align: "end" as const,
        },
      },
    ];
  };

  const start = async () => {
    if (!import.meta.client) return;

    // Lazy-load runtime + base styles + Roofly skin so prod/uat bundles stay clean.
    const [{ driver }] = await Promise.all([
      import("driver.js"),
      import("driver.js/dist/driver.css"),
      import("~/assets/css/driver-tour.css"),
    ]);

    const isMobile = window.matchMedia("(max-width: 767px)").matches;

    const tour = driver({
      showProgress: true,
      allowClose: true,
      popoverClass: "roofly-tour",
      nextBtnText: t("demo.tour.next"),
      prevBtnText: t("demo.tour.prev"),
      doneBtnText: t("demo.tour.done"),
      steps: buildSteps(isMobile),
      onDestroyed: () => {
        try {
          localStorage.setItem(STORAGE_KEY, "true");
        } catch {
          // private mode / quota — silently skip; tour will replay on next visit
        }
      },
    });

    tour.drive();
  };

  const maybeAutoStart = () => {
    if (!isDemo || !import.meta.client) return;
    try {
      if (localStorage.getItem(STORAGE_KEY) === "true") return;
    } catch {
      return;
    }
    // Small delay so the dashboard layout has settled and anchors exist.
    setTimeout(() => start(), 700);
  };

  const reset = () => {
    if (!import.meta.client) return;
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch {
      // ignore
    }
  };

  return { start, maybeAutoStart, reset };
};
