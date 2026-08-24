/**
 * Loads Google Identity Services' `accounts.google.com/gsi/client` script
 * exactly once per page session and tracks its terminal state so that a
 * later caller — a second mount of `GoogleSignInButton.vue` after
 * navigating from /auth/login to /auth/register, say — can resolve or
 * reject synchronously instead of waiting on browser events that already
 * fired once and will never fire again.
 *
 * This MUST live in a plain `.ts` module, not inside a `<script setup>`
 * block. `<script setup>` top-level bindings are compiled into the
 * component's `setup()` function, so a `let` declared there is
 * re-initialised on every new component instance — it only looks
 * module-scoped. A plain module's top-level bindings are evaluated exactly
 * once, the first time the module is imported, and every importer (every
 * mount of the component, across every page that uses it) shares that same
 * evaluation for the lifetime of the bundle — real module scope, not
 * per-instance state wearing a comment that claims otherwise.
 */

export type GoogleId = {
  initialize: (o: { client_id: string; callback: (r: { credential: string }) => void; ux_mode: "popup" }) => void;
  renderButton: (el: HTMLElement, o: Record<string, string | number>) => void;
};

export const getGis = (): GoogleId | null =>
  (window as unknown as { google?: { accounts?: { id?: GoogleId } } }).google?.accounts?.id ?? null;

const GIS_SRC = "https://accounts.google.com/gsi/client";
const DEFAULT_TIMEOUT_MS = 8000;

type GisLoadState = "idle" | "loading" | "ready" | "failed";

// Module-level — see the file-header note above for why this genuinely
// persists across every component instance that imports this module,
// unlike an equivalent declaration inside a `<script setup>` block.
let state: GisLoadState = "idle";
let loadPromise: Promise<void> | null = null;

/**
 * Resolves once GIS is ready to use; rejects if it failed to load (blocked,
 * offline, or a script that never fires `load`/`error` within `timeoutMs`).
 * Safe to call from every mount — a call after a prior failure rejects
 * immediately, with no DOM work and no event wait.
 */
export const loadGisScript = (timeoutMs = DEFAULT_TIMEOUT_MS): Promise<void> => {
  if (getGis() || state === "ready") return Promise.resolve();
  if (state === "failed") return Promise.reject(new Error("gis"));
  if (loadPromise) return loadPromise;

  state = "loading";
  loadPromise = new Promise<void>((resolve, reject) => {
    let settled = false;
    const settle = (ok: boolean) => {
      if (settled) return;
      settled = true;
      window.clearTimeout(timeoutId);
      state = ok ? "ready" : "failed";
      if (ok) resolve();
      else reject(new Error("gis"));
    };

    // Belt-and-braces: a script that neither loads nor errors (a proxy or
    // extension that just hangs the request) must still reach the fallback
    // instead of leaving every mount awaiting it forever.
    const timeoutId = window.setTimeout(() => settle(false), timeoutMs);

    const existing = document.querySelector<HTMLScriptElement>(`script[src^="${GIS_SRC}"]`);
    if (existing) {
      existing.addEventListener("load", () => settle(true), { once: true });
      existing.addEventListener("error", () => settle(false), { once: true });
      return;
    }

    const s = document.createElement("script");
    s.src = GIS_SRC;
    s.async = true;
    s.defer = true;
    s.addEventListener("load", () => settle(true), { once: true });
    s.addEventListener("error", () => settle(false), { once: true });
    document.head.appendChild(s);
  });

  return loadPromise;
};
