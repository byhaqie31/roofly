<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from "reka-ui";
import Select from "~/components/ui/Select.vue";
import NoAccess from "~/components/admin/NoAccess.vue";
import EnquiryMessagesPanel from "~/components/admin/EnquiryMessagesPanel.vue";
import WaitlistPanel from "~/components/admin/WaitlistPanel.vue";

// Two inboxes on one page: Messages (help-button issues / feedback / questions from
// owners and tenants — support.manage) and Waitlist (coming-soon sign-ups with
// Invite — analytics.view). Each tab shows only with its permission; ?tab= picks one.
definePageMeta({ layout: "admin" });
const { can } = useAdminPermissions();
const { t } = useI18n();
useHead({ title: () => t("admin.nav.enquiries") });

const route = useRoute();
const router = useRouter();

type Tab = "messages" | "waitlist";
const available = computed<Tab[]>(() => [
  ...(can("support.manage") ? (["messages"] as const) : []),
  ...(can("analytics.view") ? (["waitlist"] as const) : []),
]);

const initial = String(route.query.tab ?? "") as Tab;
const activeTab = ref<Tab>(available.value.includes(initial) ? initial : (available.value[0] ?? "messages"));
watch(activeTab, (tab) => {
  // Drop the other tab's filters from the URL when switching.
  router.replace({ query: tab === available.value[0] ? {} : { tab } });
});

const newCount = ref(0);
const tabOptions = computed(() => available.value.map((tab) => ({
  value: tab,
  label: tab === "messages" && newCount.value ? `${t("admin.enquiries.tabs.messages")} (${newCount.value})` : t(`admin.enquiries.tabs.${tab}`),
})));

const tabTriggerClass =
  "-mb-px inline-flex items-center gap-2 border-b-2 border-transparent px-4 py-2 text-body text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus data-[state=active]:border-ink data-[state=active]:text-ink";
</script>

<template>
  <NoAccess v-if="available.length === 0" permission="support.manage" />
  <div v-else>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.enquiries.title") }}</h1>
      <p class="mt-2 text-caption text-ink-muted">{{ t("admin.enquiries.subtitle") }}</p>
    </header>

    <TabsRoot v-model="activeTab">
      <!-- Mobile: dropdown picker. Desktop: tab strip. -->
      <div v-if="available.length > 1" class="mb-4 sm:hidden">
        <Select v-model="activeTab" :options="tabOptions" />
      </div>
      <TabsList v-if="available.length > 1" class="mb-6 hidden gap-1 border-b border-line-passive sm:flex">
        <TabsTrigger v-for="tab in available" :key="tab" :value="tab" :class="tabTriggerClass">
          {{ t(`admin.enquiries.tabs.${tab}`) }}
          <span
            v-if="tab === 'messages' && newCount"
            class="inline-flex min-w-5 items-center justify-center rounded-pill bg-accent px-1.5 text-micro font-semibold text-white tabular-nums"
          >{{ newCount }}</span>
        </TabsTrigger>
      </TabsList>

      <TabsContent v-if="available.includes('messages')" value="messages" class="outline-none">
        <EnquiryMessagesPanel @update:new-count="newCount = $event" />
      </TabsContent>
      <TabsContent v-if="available.includes('waitlist')" value="waitlist" class="outline-none">
        <WaitlistPanel />
      </TabsContent>
    </TabsRoot>
  </div>
</template>
