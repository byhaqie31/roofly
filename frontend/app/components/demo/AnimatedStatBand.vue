<script setup lang="ts">
interface Stat {
  value: number;
  suffix: string;
  label: string;
}

const stats: Stat[] = [
  { value: 8, suffix: "h", label: "saved per month, per property" },
  { value: 98, suffix: "%", label: "on-time rent with WhatsApp reminders" },
  { value: 5, suffix: "min", label: "to send a tenant their first invoice" },
];

const root = ref<HTMLElement | null>(null);
const animatedValues = ref<number[]>(stats.map(() => 0));
let observer: IntersectionObserver | null = null;
let hasAnimated = false;

const animate = () => {
  if (hasAnimated) return;
  hasAnimated = true;

  stats.forEach((stat, i) => {
    const start = performance.now();
    const duration = 1200;
    const step = (now: number) => {
      const elapsed = now - start;
      const t = Math.min(elapsed / duration, 1);
      // easeOutCubic — fast at first, settles into final value
      const eased = 1 - Math.pow(1 - t, 3);
      animatedValues.value[i] = Math.round(stat.value * eased);
      if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  });
};

onMounted(() => {
  if (!root.value || !("IntersectionObserver" in window)) {
    // Fallback for older environments — just animate immediately.
    animate();
    return;
  }
  observer = new IntersectionObserver(
    (entries) => {
      if (entries[0]?.isIntersecting) {
        animate();
        observer?.disconnect();
      }
    },
    { threshold: 0.4 },
  );
  observer.observe(root.value);
});

onUnmounted(() => {
  observer?.disconnect();
});
</script>

<template>
  <div ref="root" class="grid grid-cols-3 gap-5">
    <div v-for="(stat, i) in stats" :key="i">
      <p
        class="text-display-sub font-semibold tracking-tight tabular-nums leading-none"
        style="color: #e76a3f"
      >
        {{ animatedValues[i] }}<span class="text-card-title">{{ stat.suffix }}</span>
      </p>
      <p
        class="mt-2 text-micro leading-snug"
        style="color: rgba(247, 244, 237, 0.7)"
      >
        {{ stat.label }}
      </p>
    </div>
  </div>
</template>
