import { onBeforeUnmount, onMounted, ref } from "vue";

/**
 * Reactive breakpoint helpers tied to the same `sm` cutoff (640px) used
 * throughout UI-STANDARDS § 11. SSR-safe — `isMobile` defaults to `false`
 * until mounted on the client.
 */
export const useBreakpoint = () => {
  const isMobile = ref(false);
  let mq: MediaQueryList | null = null;

  const sync = (e: MediaQueryListEvent | MediaQueryList) => {
    isMobile.value = e.matches;
  };

  onMounted(() => {
    mq = window.matchMedia("(max-width: 639px)");
    sync(mq);
    mq.addEventListener("change", sync);
  });

  onBeforeUnmount(() => {
    mq?.removeEventListener("change", sync);
  });

  return { isMobile };
};
