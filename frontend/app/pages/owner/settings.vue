<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { TabsRoot, TabsList, TabsTrigger, TabsContent } from "reka-ui";
import Card from "~/components/ui/Card.vue";
import Select from "~/components/ui/Select.vue";
import SettingsProfileForm from "~/components/owner/SettingsProfileForm.vue";
import SettingsPreferencesForm from "~/components/owner/SettingsPreferencesForm.vue";
import SettingsNotificationsForm from "~/components/owner/SettingsNotificationsForm.vue";
import SettingsPlanPanel from "~/components/owner/SettingsPlanPanel.vue";
import type { OwnerAccount, Plan } from "~/types/owner";

definePageMeta({ layout: "owner" });

const { t } = useI18n();
useHead({ title: () => t("owner.nav.settings") });

const account = ref<OwnerAccount | null>(null);
const plans = ref<Plan[]>([]);
const loading = ref(true);
const activeTab = ref<string>("profile");

const tabOptions = computed(() => [
  { value: "profile", label: t("owner.settings.tabs.profile") },
  { value: "preferences", label: t("owner.settings.tabs.preferences") },
  { value: "notifications", label: t("owner.settings.tabs.notifications") },
  { value: "plan", label: t("owner.settings.tabs.plan") },
]);

onMounted(async () => {
  try {
    const settings = useOwnerSettings();
    const [acc, ps] = await Promise.all([settings.get(), settings.listPlans()]);
    account.value = acc;
    plans.value = ps;
  } finally {
    loading.value = false;
  }
});

const onSaved = (updated: OwnerAccount) => {
  account.value = updated;
};

const tabTriggerClass =
  "-mb-px inline-flex items-center border-b-2 border-transparent px-4 py-2 text-body text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus data-[state=active]:border-ink data-[state=active]:text-ink";
</script>

<template>
  <div>
    <header class="mb-6">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.nav.settings") }}
      </h1>
      <p class="mt-2 text-caption text-ink-muted">
        {{ t("owner.settings.subtitle") }}
      </p>
    </header>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p>
    </Card>

    <template v-else-if="account">
      <Card padding="loose">
        <TabsRoot v-model="activeTab">
          <!-- Mobile: dropdown picker. Desktop: tab strip. -->
          <div class="mb-6 sm:hidden">
            <Select v-model="activeTab" :options="tabOptions" />
          </div>
          <TabsList class="mb-6 hidden flex-wrap gap-1 border-b border-line-passive sm:flex">
            <TabsTrigger value="profile" :class="tabTriggerClass">
              {{ t("owner.settings.tabs.profile") }}
            </TabsTrigger>
            <TabsTrigger value="preferences" :class="tabTriggerClass">
              {{ t("owner.settings.tabs.preferences") }}
            </TabsTrigger>
            <TabsTrigger value="notifications" :class="tabTriggerClass">
              {{ t("owner.settings.tabs.notifications") }}
            </TabsTrigger>
            <TabsTrigger value="plan" :class="tabTriggerClass">
              {{ t("owner.settings.tabs.plan") }}
            </TabsTrigger>
          </TabsList>

          <TabsContent value="profile" class="outline-none">
            <SettingsProfileForm :account="account" @saved="onSaved" />
          </TabsContent>
          <TabsContent value="preferences" class="outline-none">
            <SettingsPreferencesForm :account="account" @saved="onSaved" />
          </TabsContent>
          <TabsContent value="notifications" class="outline-none">
            <SettingsNotificationsForm :account="account" @saved="onSaved" />
          </TabsContent>
          <TabsContent value="plan" class="outline-none">
            <SettingsPlanPanel :plans="plans" :current-tier="account.planTier" />
          </TabsContent>
        </TabsRoot>
      </Card>
    </template>
  </div>
</template>
