/**
 * Demo product tour wrapper around driver.js.
 *
 * Two shells, one engine: pass `shell` to scope the tour to the owner or
 * tenant app. Each shell has its own step list, home path (where anchors
 * live), and localStorage "seen" flag so auto-start fires once per shell
 * per browser. Driver.js + its CSS are dynamically imported so they never
 * enter the non-demo bundle (the trigger button and auto-start are gated by
 * `isDemo` upstream). Re-runnable from the topbar.
 */
export type TourShell = "owner" | "tenant";

const STORAGE_KEY: Record<TourShell, string> = {
  owner: "roofly_tour_owner_seen",
  tenant: "roofly_tour_tenant_seen",
};

const HOME_PATH: Record<TourShell, string> = {
  owner: "/owner",
  tenant: "/tenant",
};

export const useDemoTour = (shell: TourShell = "owner") => {
  const { isDemo } = useEnv();
  const { t } = useI18n();

  // The sidebar/mobile-menu anchors exist in both shells with the same
  // selectors — only the copy differs, keyed off the shell's step namespace.
  const navStep = (ns: string, isMobile: boolean) =>
    isMobile
      ? {
          element: '[data-tour="mobile-menu"]',
          popover: {
            title: t(`${ns}.mobileMenu.title`),
            description: t(`${ns}.mobileMenu.description`),
            side: "bottom" as const,
            align: "start" as const,
          },
        }
      : {
          element: '[data-tour="sidebar"]',
          popover: {
            title: t(`${ns}.sidebar.title`),
            description: t(`${ns}.sidebar.description`),
            side: "right" as const,
            align: "start" as const,
          },
        };

  const feedbackStep = (isMobile: boolean) => ({
    element: '[data-tour="feedback"]',
    popover: {
      title: t("demo.tour.steps.feedback.title"),
      description: t("demo.tour.steps.feedback.description"),
      // Bottom-right floating button: on mobile float the popover above it,
      // on desktop float it to the left so it doesn't cover the content.
      side: isMobile ? ("top" as const) : ("left" as const),
      align: "end" as const,
    },
  });

  const buildOwnerSteps = (isMobile: boolean) => {
    const ns = "demo.tour.steps";
    return [
      {
        popover: {
          title: t(`${ns}.welcome.title`),
          description: t(`${ns}.welcome.description`),
        },
      },
      navStep(ns, isMobile),
      {
        element: '[data-tour="stats"]',
        popover: {
          title: t(`${ns}.stats.title`),
          description: t(`${ns}.stats.description`),
          side: "bottom" as const,
        },
      },
      {
        element: '[data-tour="income-chart"]',
        popover: {
          title: t(`${ns}.incomeChart.title`),
          description: t(`${ns}.incomeChart.description`),
          side: "top" as const,
        },
      },
      {
        element: '[data-tour="attention"]',
        popover: {
          title: t(`${ns}.attention.title`),
          description: t(`${ns}.attention.description`),
          side: "top" as const,
        },
      },
      feedbackStep(isMobile),
    ];
  };

  const buildTenantSteps = (isMobile: boolean) => {
    const ns = "demo.tour.tenantSteps";
    return [
      {
        popover: {
          title: t(`${ns}.welcome.title`),
          description: t(`${ns}.welcome.description`),
        },
      },
      navStep(ns, isMobile),
      {
        element: '[data-tour="rent"]',
        popover: {
          title: t(`${ns}.rent.title`),
          description: t(`${ns}.rent.description`),
          side: "bottom" as const,
        },
      },
      {
        element: '[data-tour="actions"]',
        popover: {
          title: t(`${ns}.actions.title`),
          description: t(`${ns}.actions.description`),
          side: "top" as const,
        },
      },
      {
        element: '[data-tour="issues"]',
        popover: {
          title: t(`${ns}.issues.title`),
          description: t(`${ns}.issues.description`),
          side: "top" as const,
        },
      },
      feedbackStep(isMobile),
    ];
  };

  const buildSteps = (isMobile: boolean) =>
    shell === "tenant" ? buildTenantSteps(isMobile) : buildOwnerSteps(isMobile);

  const start = async () => {
    if (!import.meta.client) return;

    // Tour anchors all live on the shell's home page. If the trigger fires
    // from another page in the shell, bounce back first so every step has a
    // real DOM target to attach to.
    const route = useRoute();
    if (route.path !== HOME_PATH[shell]) {
      await navigateTo(HOME_PATH[shell]);
      // Wait for the dashboard to mount + paint before driver.js measures.
      await new Promise((resolve) => setTimeout(resolve, 400));
    }

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
          localStorage.setItem(STORAGE_KEY[shell], "true");
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
      if (localStorage.getItem(STORAGE_KEY[shell]) === "true") return;
    } catch {
      return;
    }
    // Small delay so the dashboard layout has settled and anchors exist.
    setTimeout(() => start(), 700);
  };

  const reset = () => {
    if (!import.meta.client) return;
    try {
      localStorage.removeItem(STORAGE_KEY[shell]);
    } catch {
      // ignore
    }
  };

  return { start, maybeAutoStart, reset };
};
