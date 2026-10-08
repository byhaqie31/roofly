// Which paths fire the marketing-funnel beacon (composables/useTrack.ts,
// plugins/track.client.ts). Public pages only — never /owner, /tenant or
// /admin. The privacy notice describes this list; keep the two in step.
// Pure, Vitest-covered (trackedPaths.test.ts).

export const TRACKED_PREFIXES = ["/coming-soon", "/demo", "/auth", "/legal"];

export const isTrackedPath = (path: string) =>
  path === "/" || TRACKED_PREFIXES.some((p) => path === p || path.startsWith(`${p}/`));
