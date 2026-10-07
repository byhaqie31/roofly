/**
 * wa.me deep links for sharing with a tenant (spec 2026-10-07 § 4.3).
 *
 * WhatsApp wants the number as bare digits with the country code and no "+".
 * Malaysian numbers are usually typed as "012-345 6789"; a leading 0 is the
 * trunk prefix, which becomes "60". Numbers that already carry a code ("+60",
 * "+65") are used as-is.
 */
const MALAYSIA = "60";
const MIN_DIGITS = 7;

export const whatsappNumber = (phone: string): string | null => {
  const digits = phone.replace(/\D/g, "");
  if (digits.length < MIN_DIGITS) return null;
  if (phone.trim().startsWith("+")) return digits;
  if (digits.startsWith("0")) return MALAYSIA + digits.slice(1);
  return digits;
};

/** Opens a chat with the number (or WhatsApp's contact picker when it's unusable) and the message prefilled. */
export const whatsappShareUrl = (phone: string, text: string): string =>
  `https://wa.me/${whatsappNumber(phone) ?? ""}?text=${encodeURIComponent(text)}`;
