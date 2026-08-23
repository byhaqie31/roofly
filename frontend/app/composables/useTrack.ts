import type { TrackEvent, TrackPayload } from "~/types/analytics";
import { demoTrack } from "~/demo/track";
import { apiTrack } from "~/services/api/track";

const VID_KEY = "roofly_vid";
const UTM_KEY = "roofly_utm";
export const TRACKED_PREFIXES = ["/coming-soon", "/demo", "/auth"];
export const isTrackedPath = (path: string) => path === "/" || TRACKED_PREFIXES.some((p) => path === p || path.startsWith(`${p}/`));

const read = (k: string) => { try { return localStorage.getItem(k); } catch { return null; } };
const write = (k: string, v: string) => { try { localStorage.setItem(k, v); } catch { /* private mode */ } };

export const useTrack = () => {
  const env = useEnv();
  const adapter = env.useMock ? demoTrack : apiTrack;

  const visitorId = (): string => {
    let id = read(VID_KEY);
    if (!id) { id = crypto.randomUUID(); write(VID_KEY, id); }
    return id;
  };

  /** First-touch UTM: captured once from the landing URL, reused on every event. */
  const utm = (): TrackPayload["utm"] | undefined => {
    const stored = read(UTM_KEY);
    if (stored) {
      try {
        return JSON.parse(stored);
      } catch {
        return undefined;
      }
    }
    const q = new URLSearchParams(window.location.search);
    const u = { source: q.get("utm_source") ?? undefined, medium: q.get("utm_medium") ?? undefined, campaign: q.get("utm_campaign") ?? undefined };
    if (!u.source && !u.medium && !u.campaign) return undefined;
    write(UTM_KEY, JSON.stringify(u));
    return u;
  };

  const track = (event: TrackEvent, props?: Record<string, string>) => {
    // Analytics must never break a page — swallow anything unexpected.
    try {
      if (!import.meta.client || !env.trackingEnabled) return;
      const path = window.location.pathname;
      if (!isTrackedPath(path)) return;
      const ref = document.referrer ? new URL(document.referrer).hostname : undefined;
      adapter.send({ visitorId: visitorId(), event, path, referrer: ref && ref !== window.location.hostname ? ref : undefined, utm: utm(), props, at: new Date().toISOString() });
    } catch {
      /* analytics must never break a page */
    }
  };

  return { track, visitorId };
};
