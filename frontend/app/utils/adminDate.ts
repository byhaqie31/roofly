/** Shared admin date formatting — keeps LeadDrawer, EventList, AuditTable and the analytics
 * page's date rendering identical instead of each component hand-rolling its own `fmt`. */

/** Full date + time, e.g. "23 Aug 2026, 02:15 PM". Used for audit/event timestamps. */
export const formatAdminDateTime = (iso: string): string =>
  new Date(iso).toLocaleString("en-MY", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });

/** Date only, e.g. "23 Aug 2026". Returns `fallback` for a null/empty iso string. */
export const formatAdminDate = (iso: string | null, fallback = "—"): string => {
  if (!iso) return fallback;
  return new Date(iso).toLocaleDateString("en-MY", { day: "2-digit", month: "short", year: "numeric" });
};
