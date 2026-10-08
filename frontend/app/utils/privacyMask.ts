// Frontend mirror of backend App\Support\PrivacyMask, used by the demo admin
// adapter so demo mode shows exactly what the API sends: tenants are
// owner-controlled data, admins see a masked name + email only. Keep the two
// implementations in step. Pure, Vitest-covered (privacyMask.test.ts).

export const MASK_DOTS = "•••";

/** "Aminah Binti Yusof" → "Aminah Y."; one word stays as is. */
export const maskName = (name: string | null | undefined): string => {
  const parts = (name ?? "").trim().split(/\s+/u).filter(Boolean);
  if (parts.length === 0) return MASK_DOTS;
  if (parts.length === 1) return parts[0]!;
  const last = parts[parts.length - 1]!;
  return `${parts[0]} ${Array.from(last)[0]!.toUpperCase()}.`;
};

/** "aminah.yusof@example.com" → "am•••@example.com". */
export const maskEmail = (email: string | null | undefined): string => {
  const value = (email ?? "").trim();
  const at = value.lastIndexOf("@");
  if (at <= 0) return MASK_DOTS;
  const local = Array.from(value.slice(0, at));
  const keep = local.length > 2 ? 2 : 1;
  return `${local.slice(0, keep).join("")}${MASK_DOTS}${value.slice(at)}`;
};
