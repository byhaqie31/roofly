// The production "coming soon only" rule from middleware/env.global.ts, pulled
// out as a pure function so it's Vitest-covered (comingSoonGate.test.ts).

import { isLegalPath } from "~/utils/legal";

const isAdminPath = (path: string) => path === "/admin" || path.startsWith("/admin/");

/**
 * While `comingSoonOnly` is on, every route redirects to /coming-soon except
 * the page itself, the admin back office (Enquiries) and the legal pages —
 * the coming-soon page collects personal data and links to the privacy notice.
 */
export const shouldRedirectToComingSoon = (path: string, comingSoonOnly: boolean): boolean =>
  comingSoonOnly && path !== "/coming-soon" && !isAdminPath(path) && !isLegalPath(path);
