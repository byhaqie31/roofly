<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { TabsRoot, TabsList, TabsTrigger, TabsContent } from "reka-ui";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import Modal from "~/components/ui/Modal.vue";
import Select from "~/components/ui/Select.vue";
import PropertyOverviewPanel from "~/components/owner/PropertyOverviewPanel.vue";
import PropertyDetailsForm from "~/components/owner/PropertyDetailsForm.vue";
import PropertyOwnershipForm from "~/components/owner/PropertyOwnershipForm.vue";
import PropertyUtilitiesForm from "~/components/owner/PropertyUtilitiesForm.vue";
import PropertyDocumentsPanel from "~/components/owner/PropertyDocumentsPanel.vue";
import UnitsPanel from "~/components/owner/UnitsPanel.vue";
import { useToast } from "~/composables/useToast";
import type { Property } from "~/types/property";
import { tabCompletion } from "~/utils/propertyCompletion";

definePageMeta({ layout: "owner" });
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { show } = useToast();
const { public: { features } } = useRuntimeConfig();
const documentsEnabled = features.documents;

const property = ref<Property | null>(null);
const loading = ref(true);
const showDeleteConfirm = ref(false);
const deleting = ref(false);
const activeTab = ref<string>("overview");

onMounted(async () => {
  try {
    property.value = await useProperties().get(route.params.id as string);
  } finally {
    loading.value = false;
  }
});

useHead({
  title: () => property.value?.name ?? t("owner.nav.properties"),
});

const onSaved = (updated: Property) => {
  property.value = updated;
};

const confirmDelete = async () => {
  if (!property.value) return;
  deleting.value = true;
  try {
    await useProperties().remove(property.value.id);
    show(t("owner.properties.detail.deletedToast"), "success");
    router.push("/owner/properties");
  } finally {
    deleting.value = false;
  }
};

const detailsFullness = computed(() =>
  property.value ? tabCompletion(property.value, "details") : 0,
);
const ownershipFullness = computed(() =>
  property.value ? tabCompletion(property.value, "ownership") : 0,
);
const utilitiesFullness = computed(() =>
  property.value ? tabCompletion(property.value, "utilities") : 0,
);

const tabTriggerClass =
  "-mb-px inline-flex items-center border-b-2 border-transparent px-4 py-2 text-body text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus data-[state=active]:border-ink data-[state=active]:text-ink";

// Mobile dropdown source — Documents is filtered out when the flag is off so
// the Select stays in sync with the tab strip.
const tabOptions = computed(() => {
  const base = [
    { value: "overview", label: t("owner.properties.detail.tabs.overview") },
    { value: "details", label: t("owner.properties.detail.tabs.details") },
    { value: "ownership", label: t("owner.properties.detail.tabs.ownership") },
    { value: "utilities", label: t("owner.properties.detail.tabs.utilities") },
  ];
  if (documentsEnabled) {
    base.push({
      value: "documents",
      label: t("owner.properties.detail.tabs.documents"),
    });
  }
  return base;
});
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between gap-2">
      <NuxtLink
        to="/owner/properties"
        class="inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink"
      >
        <Icon name="ArrowLeft" :size="14" />
        {{ t("owner.properties.detail.back") }}
      </NuxtLink>
      <!-- Mobile: delete pairs with the back link -->
      <Button
        v-if="property"
        variant="ghost"
        size="sm"
        class="sm:hidden"
        @click="showDeleteConfirm = true"
      >
        <Icon name="Trash2" :size="14" class="mr-1" />
        {{ t("owner.properties.detail.delete") }}
      </Button>
    </div>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("common.loading") }}
      </p>
    </Card>

    <Card v-else-if="!property" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("owner.properties.detail.notFound") }}
      </p>
    </Card>

    <template v-else>
      <header class="mb-6 sm:flex sm:items-end sm:justify-between sm:gap-4">
        <div class="sm:min-w-0">
          <Pill tone="neutral" class="mb-3">
            {{ t(`owner.properties.types.${property.type}`) }}
          </Pill>
          <h1 class="text-display-sub font-semibold tracking-snug text-ink">
            {{ property.name }}
          </h1>
          <p class="mt-2 text-caption text-ink-muted">
            {{ property.address }}, {{ property.city }}, {{ property.state }}
            {{ property.postcode }}
          </p>
        </div>
        <!-- Desktop: delete sits next to the title's supporting line -->
        <Button
          variant="ghost"
          size="sm"
          class="hidden shrink-0 sm:inline-flex"
          @click="showDeleteConfirm = true"
        >
          <Icon name="Trash2" :size="14" class="mr-1" />
          {{ t("owner.properties.detail.delete") }}
        </Button>
      </header>

      <Card padding="loose">
        <TabsRoot v-model="activeTab">
          <!-- Mobile: dropdown picker. Desktop: tab strip. -->
          <div class="mb-6 sm:hidden">
            <Select v-model="activeTab" :options="tabOptions" />
          </div>
          <TabsList class="mb-6 hidden flex-wrap gap-1 border-b border-line-passive sm:flex">
            <TabsTrigger value="overview" :class="tabTriggerClass">
              {{ t("owner.properties.detail.tabs.overview") }}
            </TabsTrigger>
            <TabsTrigger value="details" :class="tabTriggerClass">
              {{ t("owner.properties.detail.tabs.details") }}
              <Icon
                v-if="detailsFullness === 100"
                name="Check"
                :size="12"
                class="ml-1.5 text-status-active"
              />
              <span
                v-else-if="detailsFullness > 0"
                class="ml-1.5 h-1.5 w-1.5 rounded-full bg-status-maintenance"
              />
              <span
                v-else
                class="ml-1.5 h-1.5 w-1.5 rounded-full bg-line-passive"
              />
            </TabsTrigger>
            <TabsTrigger value="ownership" :class="tabTriggerClass">
              {{ t("owner.properties.detail.tabs.ownership") }}
              <Icon
                v-if="ownershipFullness === 100"
                name="Check"
                :size="12"
                class="ml-1.5 text-status-active"
              />
              <span
                v-else-if="ownershipFullness > 0"
                class="ml-1.5 h-1.5 w-1.5 rounded-full bg-status-maintenance"
              />
              <span
                v-else
                class="ml-1.5 h-1.5 w-1.5 rounded-full bg-line-passive"
              />
            </TabsTrigger>
            <TabsTrigger value="utilities" :class="tabTriggerClass">
              {{ t("owner.properties.detail.tabs.utilities") }}
              <Icon
                v-if="utilitiesFullness === 100"
                name="Check"
                :size="12"
                class="ml-1.5 text-status-active"
              />
              <span
                v-else-if="utilitiesFullness > 0"
                class="ml-1.5 h-1.5 w-1.5 rounded-full bg-status-maintenance"
              />
              <span
                v-else
                class="ml-1.5 h-1.5 w-1.5 rounded-full bg-line-passive"
              />
            </TabsTrigger>
            <TabsTrigger
              v-if="documentsEnabled"
              value="documents"
              :class="tabTriggerClass"
            >
              {{ t("owner.properties.detail.tabs.documents") }}
              <span
                class="ml-1.5 h-1.5 w-1.5 rounded-full bg-line-passive"
                :title="t('owner.properties.detail.documents.placeholderTitle')"
              />
            </TabsTrigger>
          </TabsList>

          <TabsContent value="overview" class="outline-none">
            <PropertyOverviewPanel :property="property" />
          </TabsContent>
          <TabsContent value="details" class="outline-none">
            <PropertyDetailsForm :property="property" @saved="onSaved" />
          </TabsContent>
          <TabsContent value="ownership" class="outline-none">
            <PropertyOwnershipForm :property="property" @saved="onSaved" />
          </TabsContent>
          <TabsContent value="utilities" class="outline-none">
            <PropertyUtilitiesForm :property="property" @saved="onSaved" />
          </TabsContent>
          <TabsContent
            v-if="documentsEnabled"
            value="documents"
            class="outline-none"
          >
            <PropertyDocumentsPanel />
          </TabsContent>
        </TabsRoot>
      </Card>

      <UnitsPanel :property-id="property.id" />

      <Modal
        v-model:open="showDeleteConfirm"
        :title="t('owner.properties.detail.deleteConfirm.title')"
        size="sm"
      >
        <p class="text-body text-ink">
          {{
            t("owner.properties.detail.deleteConfirm.body", {
              name: property.name,
            })
          }}
        </p>
        <template #footer>
          <Button
            variant="ghost"
            :disabled="deleting"
            @click="showDeleteConfirm = false"
          >
            {{ t("common.cancel") }}
          </Button>
          <Button
            variant="accent"
            :loading="deleting"
            @click="confirmDelete"
          >
            {{ t("owner.properties.detail.deleteConfirm.confirm") }}
          </Button>
        </template>
      </Modal>
    </template>
  </div>
</template>
