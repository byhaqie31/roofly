/**
 * Copy text to the clipboard. The async Clipboard API needs a secure context
 * and a user gesture; when it's unavailable (http://<lan-ip> during testing,
 * older WebViews) fall back to the legacy execCommand path. Resolves false
 * when neither worked so the caller can tell the user to copy by hand.
 */
export const copyToClipboard = async (text: string): Promise<boolean> => {
  if (typeof navigator !== "undefined" && navigator.clipboard?.writeText) {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch {
      // fall through to the legacy path
    }
  }
  if (typeof document === "undefined") return false;
  const area = document.createElement("textarea");
  area.value = text;
  area.setAttribute("readonly", "");
  area.style.position = "fixed";
  area.style.opacity = "0";
  document.body.appendChild(area);
  area.select();
  let ok = false;
  try {
    ok = document.execCommand("copy");
  } catch {
    ok = false;
  }
  document.body.removeChild(area);
  return ok;
};
