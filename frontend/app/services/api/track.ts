import type { TrackAdapter, TrackPayload } from "~/types/analytics";

export const apiTrack: TrackAdapter = {
  send(payload: TrackPayload) {
    const url = `${useRuntimeConfig().public.apiBase}/track`;
    const body = JSON.stringify(payload);
    try {
      if (typeof navigator !== "undefined" && navigator.sendBeacon) {
        if (navigator.sendBeacon(url, new Blob([body], { type: "application/json" }))) return;
      }
    } catch {
      // Chrome throws for a cross-origin JSON beacon (e.g. coming-soon-only prod
      // → UAT's API); fall through to fetch, which does a CORS preflight.
    }
    try {
      void $fetch(url, { method: "POST", body: payload, keepalive: true }).catch(() => {});
    } catch {
      // analytics must never break a page
    }
  },
};
