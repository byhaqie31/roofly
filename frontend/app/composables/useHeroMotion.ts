import { gsap } from "gsap";

/**
 * Marketing-hero motion. Client-only; every caller must guard with
 * onMounted / import.meta.client. Honours prefers-reduced-motion by
 * collapsing to instant states — content is always visible, never gated
 * on an animation finishing.
 */
export const useHeroMotion = () => {
  const reduced = () =>
    typeof window !== "undefined" &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /** Staggered entrance: eyebrow → headline → subhead → CTAs. */
  const enter = (targets: Element[]) => {
    if (reduced()) {
      gsap.set(targets, { autoAlpha: 1, y: 0 });
      return null;
    }
    return gsap.fromTo(
      targets,
      { autoAlpha: 0, y: 28 },
      { autoAlpha: 1, y: 0, duration: 0.9, ease: "power3.out", stagger: 0.12 },
    );
  };

  /** Slow Ken-Burns drift on the backdrop. Returns the tween for cleanup. */
  const drift = (el: Element) => {
    if (reduced()) return null;
    return gsap.fromTo(
      el,
      { scale: 1.04, xPercent: -1.5 },
      { scale: 1.1, xPercent: 1.5, duration: 24, ease: "sine.inOut", yoyo: true, repeat: -1 },
    );
  };

  /**
   * Background slideshow for the hero. `slides` are stacked, absolutely
   * positioned images in DOM order; slides[0] is rendered visible
   * server-side so first paint always has an image. Every `hold` seconds
   * the next slide fades in *on top* of the current one over `fade`
   * seconds (the outgoing slide stays opaque underneath, so the crossfade
   * never dips to the page background), while each visible slide drifts
   * Ken-Burns style for its whole on-screen life. Pauses while the tab is
   * hidden. Reduced motion → static first slide. Returns a cleanup fn.
   *
   * Sets z-index on the slides (0 / 1 / 2) to control the crossfade, so the
   * slides' parent MUST be its own stacking context (`isolate`) — otherwise
   * those z-indexes escape and paint over the hero copy.
   */
  const slideshow = (slides: HTMLElement[], { hold = 6, fade = 1.4 } = {}) => {
    if (slides.length === 0) return () => {};
    gsap.set(slides, { autoAlpha: 0, zIndex: 0 });
    gsap.set(slides[0] as HTMLElement, { autoAlpha: 1, zIndex: 1 });
    if (slides.length < 2 || reduced()) return () => {};

    const life = hold + fade * 2;
    let i = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let alive = true;

    // Alternate drift direction per slide so consecutive moves differ.
    const driftSlide = (el: HTMLElement, dir: 1 | -1) =>
      gsap.fromTo(
        el,
        { scale: 1.04, xPercent: -1.2 * dir },
        { scale: 1.12, xPercent: 1.2 * dir, duration: life, ease: "none", overwrite: "auto" },
      );

    const step = () => {
      if (!alive) return;
      const cur = slides[i] as HTMLElement;
      i = (i + 1) % slides.length;
      const next = slides[i] as HTMLElement;
      gsap.set(next, { zIndex: 2 });
      gsap.set(cur, { zIndex: 1 });
      driftSlide(next, i % 2 === 0 ? 1 : -1);
      gsap.to(next, {
        autoAlpha: 1,
        duration: fade,
        ease: "power1.inOut",
        onComplete: () => gsap.set(cur, { autoAlpha: 0, zIndex: 0 }),
      });
      timer = setTimeout(step, (hold + fade) * 1000);
    };

    const onVisibility = () => {
      if (document.hidden) {
        if (timer) clearTimeout(timer);
        timer = null;
      } else if (alive && timer === null) {
        timer = setTimeout(step, hold * 1000);
      }
    };

    driftSlide(slides[0] as HTMLElement, 1);
    timer = setTimeout(step, hold * 1000);
    document.addEventListener("visibilitychange", onVisibility);

    return () => {
      alive = false;
      if (timer) clearTimeout(timer);
      document.removeEventListener("visibilitychange", onVisibility);
      gsap.killTweensOf(slides);
    };
  };

  /** Gentle attention bob for a badge. Returns the tween for cleanup. */
  const bob = (el: Element) => {
    if (reduced()) return null;
    return gsap.to(el, { y: -3, rotate: 10, duration: 1.1, ease: "sine.inOut", yoyo: true, repeat: -1, delay: 1.2 });
  };

  /**
   * Hover wiggle for a badge: when the pointer enters `trigger`, the badge
   * pops and shakes side-to-side, then settles. Returns a cleanup fn.
   */
  const wiggleOnHover = (trigger: Element, badge: Element, idle?: gsap.core.Tween | null) => {
    if (
      reduced() ||
      typeof window === "undefined" ||
      !window.matchMedia("(hover: hover) and (pointer: fine)").matches
    ) {
      return () => {};
    }
    let tl: gsap.core.Timeline | null = null;
    const lift = gsap.to(trigger, {
      scale: 1.04,
      y: -2,
      boxShadow: "0 12px 24px -10px rgba(0, 0, 0, 0.5)",
      duration: 0.25,
      ease: "power3.out",
      paused: true,
    });
    const out = () => lift.reverse();
    const over = () => {
      lift.play();
      if (tl?.isActive()) return;
      idle?.pause();
      tl = gsap
        .timeline({
          defaults: { ease: "power2.inOut" },
          onComplete: () => idle?.resume(),
        })
        .to(badge, { scale: 1.2, duration: 0.14, ease: "back.out(3)" }, 0)
        .to(badge, { rotate: -14, duration: 0.09 }, 0)
        .to(badge, { rotate: 18, duration: 0.1 })
        .to(badge, { rotate: -12, duration: 0.1 })
        .to(badge, { rotate: 14, duration: 0.1 })
        .to(badge, { rotate: -6, duration: 0.1 })
        .to(badge, { rotate: 10, duration: 0.12 })
        .to(badge, { scale: 1, duration: 0.2, ease: "back.out(2)" }, "-=0.1");
    };
    trigger.addEventListener("pointerenter", over);
    trigger.addEventListener("pointerleave", out);
    return () => {
      trigger.removeEventListener("pointerenter", over);
      trigger.removeEventListener("pointerleave", out);
      lift.kill();
      tl?.kill();
    };
  };

  /**
   * Word rotation: flips `el` between `words` every `interval` ms.
   * Returns a stop() function. The caller renders words[0] server-side
   * so the first paint never shows an empty slot.
   */
  const rotateWords = (el: HTMLElement, words: string[], interval = 2800) => {
    if (words.length < 2) return () => {};
    let i = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let alive = true;

    const step = () => {
      if (!alive) return;
      const next = words[(i + 1) % words.length] as string;
      if (reduced()) {
        el.textContent = next;
        i = (i + 1) % words.length;
        timer = setTimeout(step, interval);
        return;
      }
      gsap
        .timeline({ onComplete: () => { i = (i + 1) % words.length; timer = setTimeout(step, interval); } })
        .to(el, { yPercent: -60, autoAlpha: 0, duration: 0.32, ease: "power2.in" })
        .add(() => { el.textContent = next; })
        .fromTo(el, { yPercent: 60, autoAlpha: 0 }, { yPercent: 0, autoAlpha: 1, duration: 0.42, ease: "power3.out" });
    };

    timer = setTimeout(step, interval);
    return () => {
      alive = false;
      if (timer) clearTimeout(timer);
      gsap.killTweensOf(el);
    };
  };

  /** Scroll-reveal a list of cards once, in a light stagger. */
  const revealOnScroll = (targets: Element[]) => {
    if (reduced() || typeof IntersectionObserver === "undefined") {
      gsap.set(targets, { autoAlpha: 1, y: 0 });
      return () => {};
    }
    gsap.set(targets, { autoAlpha: 0, y: 24 });
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (!e.isIntersecting) return;
          gsap.to(e.target, { autoAlpha: 1, y: 0, duration: 0.7, ease: "power3.out", delay: (targets.indexOf(e.target) % 3) * 0.08 });
          io.unobserve(e.target);
        });
      },
      { threshold: 0.2 },
    );
    targets.forEach((t) => io.observe(t));
    return () => io.disconnect();
  };

  /**
   * Hover-expand for a card grid. Each card lifts + grows, its watermark
   * icon ([data-watermark]) swells and brightens, and its border warms to the
   * accent. Only binds on hover-capable pointers. Returns a cleanup fn.
   */
  const hoverExpand = (cards: HTMLElement[]) => {
    if (
      reduced() ||
      typeof window === "undefined" ||
      !window.matchMedia("(hover: hover) and (pointer: fine)").matches
    ) {
      return () => {};
    }
    const unbinders: Array<() => void> = [];
    cards.forEach((card) => {
      const mark = card.querySelector<HTMLElement>("[data-watermark]");
      const body = card.querySelector<HTMLElement>("[data-body]");
      const tl = gsap
        .timeline({ paused: true, defaults: { duration: 0.35, ease: "power3.out" } })
        .to(card, {
          scale: 1.02,
          y: -3,
          borderColor: "rgba(247, 244, 237, 0.22)",
          backgroundColor: "rgba(247, 244, 237, 0.06)",
        }, 0);
      if (mark) tl.to(mark, { scale: 1.12, opacity: 0.12 }, 0);
      if (body) tl.to(body, { color: "rgba(247, 244, 237, 0.95)" }, 0);

      const over = () => tl.play();
      const out = () => tl.reverse();
      card.addEventListener("pointerenter", over);
      card.addEventListener("pointerleave", out);
      unbinders.push(() => {
        card.removeEventListener("pointerenter", over);
        card.removeEventListener("pointerleave", out);
        tl.kill();
      });
    });
    return () => unbinders.forEach((u) => u());
  };

  return { enter, drift, slideshow, bob, wiggleOnHover, rotateWords, revealOnScroll, hoverExpand };
};
