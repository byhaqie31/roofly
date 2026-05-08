<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { TabsRoot, TabsList, TabsTrigger, TabsContent } from "reka-ui";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import Modal from "~/components/ui/Modal.vue";
import PropertyIdentityForm from "~/components/owner/PropertyIdentityForm.vue";
import PropertyBasicsForm from "~/components/owner/PropertyBasicsForm.vue";
import PropertyCostsForm from "~/components/owner/PropertyCostsForm.vue";
import UnitsPanel from "~/components/owner/UnitsPanel.vue";
import { useToast } from "~/composables/useToast";
import type { Property } from "~/types/property";

definePageMeta({ layout: "owner" });
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { show } = useToast();

const property = ref<Property | null>(null);
const loading = ref(true);
const showDeleteConfirm = ref(false);
const deleting = ref(false);

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

const tabTriggerClass =
  "-mb-px border-b-2 border-transparent px-4 py-2 text-body text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus data-[state=active]:border-ink data-[state=active]:text-ink";
</script>

<template>
  <div>
    <NuxtLink
      to="/owner/properties"
      class="mb-6 inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink"
    >
      <Icon name="ArrowLeft" :size="14" />
      {{ t("owner.properties.detail.back") }}
    </NuxtLink>

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
      <header class="mb-6">
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
      </header>

      <Card padding="loose">
        <TabsRoot default-value="basics">
          <TabsList class="mb-6 flex gap-1 border-b border-line-passive">
            <TabsTrigger value="identity" :class="tabTriggerClass">
              {{ t("owner.properties.detail.tabs.identity") }}
            </TabsTrigger>
            <TabsTrigger value="basics" :class="tabTriggerClass">
              {{ t("owner.properties.detail.tabs.basics") }}
            </TabsTrigger>
            <TabsTrigger value="costs" :class="tabTriggerClass">
              {{ t("owner.properties.detail.tabs.costs") }}
            </TabsTrigger>
          </TabsList>

          <TabsContent value="identity" class="outline-none">
            <PropertyIdentityForm :property="property" @saved="onSaved" />
          </TabsContent>
          <TabsContent value="basics" class="outline-none">
            <PropertyBasicsForm :property="property" @saved="onSaved" />
          </TabsContent>
          <TabsContent value="costs" class="outline-none">
            <PropertyCostsForm :property="property" @saved="onSaved" />
          </TabsContent>
        </TabsRoot>
      </Card>

      <UnitsPanel :property-id="property.id" />

      <div class="mt-6 flex justify-end">
        <Button variant="ghost" @click="showDeleteConfirm = true">
          <Icon name="Trash2" :size="14" class="mr-1" />
          {{ t("owner.properties.detail.delete") }}
        </Button>
      </div>

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
