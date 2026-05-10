<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from "vue";

interface AudienceFace {
  titleLead: string;
  titleAccent: string;
  description: string;
}

const { t } = useI18n();

const owner = computed<AudienceFace>(() => ({
  titleLead: t("demo.audience.owner.titleLead"),
  titleAccent: t("demo.audience.owner.titleAccent"),
  description: t("demo.audience.owner.description"),
}));

const tenant = computed<AudienceFace>(() => ({
  titleLead: t("demo.audience.tenant.titleLead"),
  titleAccent: t("demo.audience.tenant.titleAccent"),
  description: t("demo.audience.tenant.description"),
}));

const FLIP_MS = 4500;

// Binary flip: 0 = owner face up, 1 = tenant face up.
const index = ref(0);
const isFlipped = computed(() => index.value % 2 === 1);
const isPaused = ref(false);

let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  timer = setInterval(() => {
    if (isPaused.value) return;
    index.value += 1;
  }, FLIP_MS);
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});
</script>

<template>
  <!-- 3D vertical flip — the card tumbles forward around its horizontal axis.
       Both faces live in the DOM; CSS backface-visibility hides whichever side
       is facing away. perspective on the parent gives the flip its depth. -->
  <div
    class="flip-perspective"
    @mouseenter="isPaused = true"
    @mouseleave="isPaused = false"
  >
    <div class="flip-inner" :class="{ 'is-flipped': isFlipped }">
      <!-- Owner face (front) -->
      <div class="flip-face flip-front max-w-[36rem]">
        <h1
          class="text-display-section lg:text-display-hero font-semibold tracking-tight leading-[1.05]"
        >
          <span class="block" style="color: #f7f4ed">
            {{ owner.titleLead }}
          </span>
          <span class="block" style="color: #e76a3f">
            {{ owner.titleAccent }}
          </span>
        </h1>
        <p
          class="mt-6 text-body-lg max-w-[34rem]"
          style="color: rgba(247, 244, 237, 0.88)"
        >
          {{ owner.description }}
        </p>
      </div>

      <!-- Tenant face (back) -->
      <div class="flip-face flip-back max-w-[36rem]">
        <h1
          class="text-display-section lg:text-display-hero font-semibold tracking-tight leading-[1.05]"
        >
          <span class="block" style="color: #f7f4ed">
            {{ tenant.titleLead }}
          </span>
          <span class="block" style="color: #e76a3f">
            {{ tenant.titleAccent }}
          </span>
        </h1>
        <p
          class="mt-6 text-body-lg max-w-[34rem]"
          style="color: rgba(247, 244, 237, 0.88)"
        >
          {{ tenant.description }}
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* perspective gives the rotateX a sense of depth — without it the flip
   reads as a flat scale instead of a tumble. Higher = subtler depth. */
.flip-perspective {
  perspective: 1600px;
  min-height: 16rem;
}

.flip-inner {
  position: relative;
  width: 100%;
  min-height: 16rem;
  transition: transform 0.9s cubic-bezier(0.4, 0, 0.2, 1);
  transform-style: preserve-3d;
}

.flip-inner.is-flipped {
  transform: rotateX(180deg);
}

.flip-face {
  position: absolute;
  inset: 0;
  -webkit-backface-visibility: hidden;
  backface-visibility: hidden;
}

/* Counter-rotate the back face so its text reads right-side up when the
   container is flipped 180deg. */
.flip-back {
  transform: rotateX(180deg);
}

@media (min-width: 1024px) {
  .flip-perspective,
  .flip-inner {
    min-height: 22rem;
  }
}
</style>
