/**
 * Tenancy term presets for the agreement form. Malaysian convention: a
 * 12-month tenancy starting 1 January ends 31 December, i.e. the end date is
 * the same day N months on, minus one day (clamped when that month is shorter).
 */
export const AGREEMENT_TERM_PRESETS = [6, 12, 24] as const;
export type AgreementTermPreset = (typeof AGREEMENT_TERM_PRESETS)[number];

const parseIso = (iso: string): Date | null => {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) return null;
  const [y, m, d] = iso.split("-").map(Number) as [number, number, number];
  const date = new Date(Date.UTC(y, m - 1, d));
  return Number.isNaN(date.getTime()) ? null : date;
};

const toIso = (d: Date): string => d.toISOString().slice(0, 10);

/** End date for a term of `months` from `startIso`; "" when the start isn't a usable date. */
export const endDateForTerm = (startIso: string, months: number): string => {
  const start = parseIso(startIso);
  if (!start) return "";
  const year = start.getUTCFullYear();
  const monthIndex = start.getUTCMonth() + months;
  const lastDayOfTarget = new Date(Date.UTC(year, monthIndex + 1, 0)).getUTCDate();
  const sameDayLater = new Date(Date.UTC(year, monthIndex, Math.min(start.getUTCDate(), lastDayOfTarget)));
  sameDayLater.setUTCDate(sameDayLater.getUTCDate() - 1);
  return toIso(sameDayLater);
};

/** Which preset (if any) exactly produces `endIso` from `startIso`. */
export const termMonthsBetween = (startIso: string, endIso: string): AgreementTermPreset | null =>
  AGREEMENT_TERM_PRESETS.find((months) => endDateForTerm(startIso, months) === endIso) ?? null;
