import type { TrackAdapter, TrackPayload } from "~/types/analytics";

export const apiTrack: TrackAdapter = {
  send(payload: TrackPayload) {
    const url = `${useRuntimeConfig().public.apiBase}/track`;
    const body = JSON.stringify(payload);
    try {
      if (typeof navigator !== "undefined" && navigator.sendBeacon) {
        if (navigator.sendBeacon(url, new Blob([body], { type: "application/json" }))) return;
      }
      void $fetch(url, { method: "POST", body: payload, keepalive: true }).catch(() => {});
    } catch {
      // analytics must never break a page
    }
  },
};
