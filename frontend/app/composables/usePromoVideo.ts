import { computed, onBeforeUnmount, onMounted, ref, watch, type Ref } from "vue";

export type PromoVariant = "web" | "mobile";

const MD_QUERY = "(min-width: 768px)";
const BASE = "/marketing/promo";

/**
 * Shared playback for the promo reel in public/marketing/promo/ (see the README
 * there). Picks the file client-side so each device downloads only one cut,
 * autoplays muted while on screen (never for reduced-motion users) and flags a
 * missing file so callers can hide themselves or fall back.
 *
 * - `fixed`: always use this cut instead of landscape-on-md+/portrait-below.
 * - `when`: only load at all while this media query matches (e.g. a pane that
 *   is display:none on phones) — `variant` stays null otherwise.
 */
export function usePromoVideo(
  video: Ref<HTMLVideoElement | null>,
  opts: { fixed?: PromoVariant; when?: string } = {},
) {
  const variant = ref<PromoVariant | null>(null);
  const failed = ref(false);

  const src = computed(() => (variant.value ? `${BASE}/promo-${variant.value}.mp4` : ""));
  const poster = computed(() => `${BASE}/promo-${variant.value ?? opts.fixed ?? "web"}-poster.webp`);

  let mqls: MediaQueryList[] = [];
  let observer: IntersectionObserver | null = null;

  const pick = () => {
    if (opts.when && !window.matchMedia(opts.when).matches) {
      variant.value = null;
      return;
    }
    variant.value = opts.fixed ?? (window.matchMedia(MD_QUERY).matches ? "web" : "mobile");
    failed.value = false;
  };

  onMounted(() => {
    mqls = [...new Set([MD_QUERY, opts.when].filter((q): q is string => Boolean(q)))].map((q) => window.matchMedia(q));
    mqls.forEach((m) => m.addEventListener("change", pick));
    pick();
  });

  watch(video, (el) => {
    observer?.disconnect();
    if (!el || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
    observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) el.play().catch(() => {});
        else el.pause();
      },
      { threshold: 0.4 },
    );
    observer.observe(el);
  });

  onBeforeUnmount(() => {
    mqls.forEach((m) => m.removeEventListener("change", pick));
    observer?.disconnect();
  });

  return { variant, src, poster, failed };
}
