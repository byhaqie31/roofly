<script setup lang="ts">
import {
  TrendingUp,
  Smartphone,
  MessageCircle,
  KanbanSquare,
  Languages,
  FileSignature,
  BarChart3,
  MapPin,
  Users,
  type LucideIcon,
} from "lucide-vue-next";

interface UspKey {
  icon: LucideIcon;
  key: string;
}

// Same 9 USPs as RotatingUspCard, presented as a static scroll-revealed grid
// instead of an auto-rotating deck. Reuses the demo.usps.* i18n keys so EN/BM
// translations stay in one place.
const uspKeys: UspKey[] = [
  { icon: TrendingUp, key: "rentCollection" },
  { icon: Smartphone, key: "tenantsPay" },
  { icon: MessageCircle, key: "whatsapp" },
  { icon: KanbanSquare, key: "maintenance" },
  { icon: Languages, key: "bilingual" },
  { icon: FileSignature, key: "agreements" },
  { icon: BarChart3, key: "reports" },
  { icon: MapPin, key: "malaysian" },
  { icon: Users, key: "coOwners" },
];

const { t } = useI18n();
</script>

<template>
  <section class="px-6 lg:px-12 py-16 lg:py-24 max-w-6xl mx-auto">
    <header class="max-w-2xl mb-12 lg:mb-16">
      <p
        class="text-micro font-medium uppercase tracking-[0.2em] mb-4"
        style="color: #e76a3f"
      >
        {{ t("marketing.usps.eyebrow") }}
      </p>
      <h2
        class="text-display-sub md:text-display-section font-semibold tracking-tight leading-[1.05]"
        style="color: #f7f4ed"
      >
        {{ t("marketing.usps.title") }}
      </h2>
      <p
        class="mt-5 text-body-lg max-w-xl"
        style="color: rgba(247, 244, 237, 0.78)"
      >
        {{ t("marketing.usps.subtitle") }}
      </p>
    </header>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <article
        v-for="usp in uspKeys"
        :key="usp.key"
        class="relative rounded-lg p-6 overflow-hidden transition-all hover:translate-y-[-2px]"
        style="
          background: rgba(247, 244, 237, 0.04);
          border: 1px solid rgba(247, 244, 237, 0.1);
          box-shadow: 0 1px 0 rgba(247, 244, 237, 0.04) inset;
        "
      >
        <!-- Watermark icon, partially clipped right edge for accent -->
        <component
          :is="usp.icon"
          class="absolute -right-4 -bottom-4 pointer-events-none"
          :size="120"
          :stroke-width="0.5"
          style="color: #e76a3f; opacity: 0.08"
          aria-hidden="true"
        />

        <div class="relative">
          <span
            class="inline-flex items-center justify-center w-11 h-11 rounded-pill mb-4"
            style="
              background: rgba(231, 106, 63, 0.16);
              color: #e76a3f;
              box-shadow: inset 0 0 0 1px rgba(231, 106, 63, 0.2);
            "
          >
            <component :is="usp.icon" :size="20" :stroke-width="1.75" />
          </span>
          <h3
            class="text-card-title font-semibold leading-snug mb-2"
            style="color: #f7f4ed"
          >
            {{ t(`demo.usps.${usp.key}.title`) }}
          </h3>
          <p
            class="text-caption leading-snug"
            style="color: rgba(247, 244, 237, 0.72)"
          >
            {{ t(`demo.usps.${usp.key}.subtitle`) }}
          </p>
        </div>
      </article>
    </div>
  </section>
</template>
