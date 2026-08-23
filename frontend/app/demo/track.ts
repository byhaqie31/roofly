import type { TrackAdapter } from "~/types/analytics";

/** Demo never tracks — demo-roofly must generate zero rows. */
export const demoTrack: TrackAdapter = { send() {} };
