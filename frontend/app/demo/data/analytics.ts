/**
 * 90 days of deterministic marketing-site analytics so the admin analytics
 * page has a story, mirroring `AnalyticsDemoSeeder` (backend): 6–16
 * visitors/day, 1–4 page views each, ~25% try the demo, 40 waitlist leads,
 * 8 of which "convert" (every 5th) into synthetic owner ids. Seeded
 * (mulberry32, seed 2026) so the story is stable across reloads, with dates
 * relative to today. Demo-only — never imported by services/api/**.
 */
import type { AdminLead, AnalyticsOverview, AnalyticsRange, LeadSource, TrackEvent } from "~/types/analytics";

/** mulberry32 — small deterministic PRNG, good enough for demo seed data. */
const rng = (seed: number) => () => {
  let t = (seed += 0x6d2b79f5);
  t = Math.imul(t ^ (t >>> 15), t | 1);
  t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
  return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};

export interface AnalyticsEventMock {
  visitorId: string;
  event: TrackEvent;
  path: string | null;
  referrer: string | null;
  props: Record<string, unknown>;
  createdAt: string;
}

const DAY_MS = 86_400_000;
const PATHS = ["/", "/", "/", "/coming-soon", "/demo", "/demo", "/auth/register"];
const REFERRERS: (string | null)[] = [null, null, null, "google.com", "facebook.com", "lowyat.net", "instagram.com"];

const startOfUtcDay = (d: Date): number => Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate());
const pad2 = (n: number): string => String(n).padStart(2, "0");

interface LeadSeed {
  id: string;
  visitorId: string;
  email: string;
  firstSeenAt: string;
  lastSeenAt: string;
  convertedUserId: string | null;
  convertedOwnerName: string | null;
}

const buildDemoAnalytics = (): { events: AnalyticsEventMock[]; leads: AdminLead[]; leadVisitorIds: Record<string, string> } => {
  const r = rng(2026);
  const events: AnalyticsEventMock[] = [];
  const leadSeeds: LeadSeed[] = [];

  const todayStart = startOfUtcDay(new Date());
  let vidSeq = 0;
  let leadsMade = 0;
  let converted = 0;

  const push = (
    visitorId: string,
    event: TrackEvent,
    atMs: number,
    extra: Partial<Pick<AnalyticsEventMock, "path" | "referrer" | "props">> = {},
  ) => {
    events.push({
      visitorId,
      event,
      path: extra.path ?? null,
      referrer: extra.referrer ?? null,
      props: extra.props ?? {},
      createdAt: new Date(atMs).toISOString(),
    });
  };

  for (let day = 89; day >= 0; day--) {
    const dayStart = todayStart - day * DAY_MS;
    const visitors = 6 + Math.floor(r() * 11); // 6..16
    for (let v = 0; v < visitors; v++) {
      const visitorId = `v-${++vidSeq}`;
      const ref = REFERRERS[Math.floor(r() * REFERRERS.length)] ?? null;
      const t = dayStart + (480 + Math.floor(r() * 901)) * 60_000; // 08:00–23:00
      const views = 1 + Math.floor(r() * 4); // 1..4
      for (let i = 0; i < views; i++) {
        const path = PATHS[Math.floor(r() * PATHS.length)] ?? "/";
        push(visitorId, "page_view", t + i * 2 * 60_000, { path, referrer: i === 0 ? ref : null });
      }
      if (Math.floor(r() * 100) + 1 <= 25) {
        push(visitorId, "demo_enter", t + 10 * 60_000, { props: { role: r() < 0.5 ? "owner" : "tenant" } });
        if (Math.floor(r() * 100) + 1 <= 20) {
          push(visitorId, "demo_feedback_click", t + 15 * 60_000);
        }
      }
      if (leadsMade < 40 && Math.floor(r() * 100) + 1 <= 5) {
        const n = ++leadsMade;
        const email = `lead${pad2(n)}@example.com`;
        const firstSeenAtMs = t + 20 * 60_000;
        push(visitorId, "waitlist_signup", firstSeenAtMs, { props: { email } });

        let convertedUserId: string | null = null;
        let convertedOwnerName: string | null = null;
        let lastSeenAtMs = firstSeenAtMs;
        if (converted < 8 && n % 5 === 0) {
          converted++;
          convertedUserId = `o-lead-${pad2(n)}`;
          convertedOwnerName = `Lead ${pad2(n)}`;
          lastSeenAtMs = firstSeenAtMs + 2 * DAY_MS;
          push(visitorId, "register", lastSeenAtMs, { path: "/auth/register", props: { email, userId: convertedUserId } });
        }

        leadSeeds.push({
          id: `lead-${pad2(n)}`,
          visitorId,
          email,
          firstSeenAt: new Date(firstSeenAtMs).toISOString(),
          lastSeenAt: new Date(lastSeenAtMs).toISOString(),
          convertedUserId,
          convertedOwnerName,
        });
      }
    }
  }

  const pageViewsByVisitor = new Map<string, number>();
  const demoEnteredVisitors = new Set<string>();
  for (const e of events) {
    if (e.event === "page_view") pageViewsByVisitor.set(e.visitorId, (pageViewsByVisitor.get(e.visitorId) ?? 0) + 1);
    if (e.event === "demo_enter") demoEnteredVisitors.add(e.visitorId);
  }

  const leads: AdminLead[] = leadSeeds.map((l) => ({
    id: l.id,
    email: l.email,
    source: "waitlist" as LeadSource,
    firstSeenAt: l.firstSeenAt,
    lastSeenAt: l.lastSeenAt,
    pageViews: pageViewsByVisitor.get(l.visitorId) ?? 0,
    demoEntered: demoEnteredVisitors.has(l.visitorId),
    convertedUserId: l.convertedUserId,
    convertedOwnerName: l.convertedOwnerName,
  }));

  const leadVisitorIds = Object.fromEntries(leadSeeds.map((l) => [l.id, l.visitorId]));

  return { events, leads, leadVisitorIds };
};

const built = buildDemoAnalytics();

export const analyticsEventsMock: AnalyticsEventMock[] = built.events;
export const leadsMock: AdminLead[] = built.leads;
/** leadId → visitorId — internal join key so the demo adapter can pull a lead's event history. Not part of the AdminLead contract. */
export const leadVisitorIds: Record<string, string> = built.leadVisitorIds;

// ── Overview arithmetic — mirrors AnalyticsController@overview ──────────────
const toDateStr = (ms: number): string => new Date(ms).toISOString().slice(0, 10);

export const computeOverview = (range: AnalyticsRange): AnalyticsOverview => {
  const toStart = range.to ? Date.parse(`${range.to}T00:00:00.000Z`) : startOfUtcDay(new Date());
  const toEnd = toStart + DAY_MS - 1;
  const fromStart = range.from ? Date.parse(`${range.from}T00:00:00.000Z`) : toStart - 29 * DAY_MS;
  const days = Math.round((toEnd - fromStart) / DAY_MS);

  const inRange = analyticsEventsMock.filter((e) => {
    const t = Date.parse(e.createdAt);
    return t >= fromStart && t <= toEnd;
  });

  const visitorIdsInRange = new Set(inRange.map((e) => e.visitorId));

  // First-ever event per visitor (across ALL events, not just in-range) to detect new vs. returning.
  const firstSeenByVisitor = new Map<string, number>();
  for (const e of analyticsEventsMock) {
    if (!visitorIdsInRange.has(e.visitorId)) continue;
    const t = Date.parse(e.createdAt);
    const prev = firstSeenByVisitor.get(e.visitorId);
    if (prev === undefined || t < prev) firstSeenByVisitor.set(e.visitorId, t);
  }
  let newVisitors = 0;
  for (const t of firstSeenByVisitor.values()) if (t >= fromStart) newVisitors++;

  const views = inRange.filter((e) => e.event === "page_view").length;
  const demoEntries = inRange.filter((e) => e.event === "demo_enter").length;
  const demoVisitors = new Set(inRange.filter((e) => e.event === "demo_enter").map((e) => e.visitorId)).size;

  const leadsInRange = leadsMock.filter((l) => {
    const t = Date.parse(l.firstSeenAt);
    return t >= fromStart && t <= toEnd;
  });
  const leads = leadsInRange.length;
  // server link is the source of truth; the client register beacon is timeline-only —
  // registrations (and the funnel's "registered" figure) come from Lead.convertedUserId,
  // never from the client-fired 'register' analytics event.
  const registeredLeadsInRange = leadsInRange.filter((l) => l.convertedUserId !== null);
  const registeredLeads = registeredLeadsInRange.length;

  const visitors = visitorIdsInRange.size;

  const dayKeys: string[] = [];
  for (let d = fromStart; d <= toEnd; d += DAY_MS) dayKeys.push(toDateStr(d));

  const bucketCount = (predicate: (e: AnalyticsEventMock) => boolean, distinctVisitor: boolean): number[] => {
    const byDay = new Map<string, Set<string> | number>();
    for (const e of inRange) {
      if (!predicate(e)) continue;
      const key = toDateStr(Date.parse(e.createdAt));
      if (distinctVisitor) {
        const set = (byDay.get(key) as Set<string> | undefined) ?? new Set<string>();
        set.add(e.visitorId);
        byDay.set(key, set);
      } else {
        byDay.set(key, ((byDay.get(key) as number | undefined) ?? 0) + 1);
      }
    }
    return dayKeys.map((k) => {
      const v = byDay.get(k);
      if (v === undefined) return 0;
      return distinctVisitor ? (v as Set<string>).size : (v as number);
    });
  };

  const leadsByDay = new Map<string, number>();
  for (const l of leadsInRange) {
    const key = toDateStr(Date.parse(l.firstSeenAt));
    leadsByDay.set(key, (leadsByDay.get(key) ?? 0) + 1);
  }

  const registeredLeadsByDay = new Map<string, number>();
  for (const l of registeredLeadsInRange) {
    const key = toDateStr(Date.parse(l.firstSeenAt));
    registeredLeadsByDay.set(key, (registeredLeadsByDay.get(key) ?? 0) + 1);
  }

  const topPagesMap = new Map<string, number>();
  for (const e of inRange) {
    if (e.event !== "page_view" || !e.path) continue;
    topPagesMap.set(e.path, (topPagesMap.get(e.path) ?? 0) + 1);
  }
  const topPages = [...topPagesMap.entries()]
    .sort((a, b) => b[1] - a[1])
    .slice(0, 10)
    .map(([path, viewsCount]) => ({ path, views: viewsCount }));

  const referrersMap = new Map<string, Set<string>>();
  for (const e of inRange) {
    if (e.event !== "page_view") continue;
    const key = e.referrer ?? "direct";
    const set = referrersMap.get(key) ?? new Set<string>();
    set.add(e.visitorId);
    referrersMap.set(key, set);
  }
  const referrers = [...referrersMap.entries()]
    .sort((a, b) => b[1].size - a[1].size || a[0].localeCompare(b[0]))
    .slice(0, 10)
    .map(([referrer, set]) => ({ referrer, visitors: set.size }));

  return {
    range: { from: toDateStr(fromStart), to: toDateStr(toEnd), days },
    tiles: {
      views,
      visitors,
      newVisitors,
      demoEntries,
      leads,
      registrations: registeredLeads,
      conversionPct: visitors > 0 ? Math.round((registeredLeads / visitors) * 100) : 0,
    },
    series: {
      days: dayKeys,
      views: bucketCount((e) => e.event === "page_view", false),
      visitors: bucketCount(() => true, true),
      leads: dayKeys.map((k) => leadsByDay.get(k) ?? 0),
      registrations: dayKeys.map((k) => registeredLeadsByDay.get(k) ?? 0),
    },
    funnel: { visitors, demo: demoVisitors, leads, registered: registeredLeads },
    topPages,
    referrers,
  };
};
