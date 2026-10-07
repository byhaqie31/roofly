/**
 * Malaysian MyKad helpers (mirrors backend App\Support\MyKad).
 *
 * Canonical storage form is dashed `YYMMDD-PB-####`, but nobody should have
 * to type the dashes: inputs accept 12 bare digits, format live, and the
 * birth date is derived from the first six digits.
 */
export const MYKAD_DIGITS = 12;

const digitsOf = (input: string): string => input.replace(/\D/g, "");

/** 12 digits in any spacing → canonical dashed form; anything else → null. */
export const normalizeMyKad = (input: string): string | null => {
  const d = digitsOf(input);
  if (d.length !== MYKAD_DIGITS) return null;
  return `${d.slice(0, 6)}-${d.slice(6, 8)}-${d.slice(8)}`;
};

/** Live formatting while typing: digits only, dashes appear after the 6th and 8th. */
export const formatMyKadInput = (input: string): string => {
  const d = digitsOf(input).slice(0, MYKAD_DIGITS);
  if (d.length <= 6) return d;
  if (d.length <= 8) return `${d.slice(0, 6)}-${d.slice(6)}`;
  return `${d.slice(0, 6)}-${d.slice(6, 8)}-${d.slice(8)}`;
};

/**
 * YYMMDD → ISO date. Two-digit years take the current century unless that
 * would put the birthday in the future, in which case the previous one.
 */
export const dateOfBirthFromMyKad = (input: string, today: Date = new Date()): string | null => {
  const d = digitsOf(input);
  if (d.length < 6) return null;
  const yy = Number(d.slice(0, 2));
  const mm = Number(d.slice(2, 4));
  const dd = Number(d.slice(4, 6));
  if (mm < 1 || mm > 12 || dd < 1 || dd > 31) return null;

  const century = Math.floor(today.getUTCFullYear() / 100) * 100;
  let year = century + yy;
  if (Date.UTC(year, mm - 1, dd) > today.getTime()) year -= 100;

  const date = new Date(Date.UTC(year, mm - 1, dd));
  if (date.getUTCMonth() !== mm - 1 || date.getUTCDate() !== dd) return null; // e.g. 30 Feb overflowed
  return date.toISOString().slice(0, 10);
};
