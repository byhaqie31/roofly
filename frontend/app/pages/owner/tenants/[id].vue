<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { TabsRoot, TabsList, TabsTrigger, TabsContent } from "reka-ui";
import { computed } from "vue";
import Card from "~/components/ui/Card.vue";
import Pill from "~/components/ui/Pill.vue";
import Icon from "~/components/ui/Icon.vue";
import Button from "~/components/ui/Button.vue";
import Modal from "~/components/ui/Modal.vue";
import Select from "~/components/ui/Select.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import TenantIdentityForm from "~/components/owner/TenantIdentityForm.vue";
import TenantPersonalForm from "~/components/owner/TenantPersonalForm.vue";
import TenantEmergencyContactForm from "~/components/owner/TenantEmergencyContactForm.vue";
import { useToast } from "~/composables/useToast";
import type { Tenant, TenantStatus } from "~/types/tenant";

definePageMeta({ layout: "owner" });
const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const { show } = useToast();
const { public: { features } } = useRuntimeConfig();
const documentsEnabled = features.documents;

const tenant = ref<Tenant | null>(null);
const loading = ref(true);
const showDeleteConfirm = ref(false);
const deleting = ref(false);
const activeTab = ref<string>("identity");

const tabOptions = computed(() => [
  { value: "identity", label: t("owner.tenants.detail.tabs.identity") },
  { value: "personal", label: t("owner.tenants.detail.tabs.personal") },
  { value: "emergency", label: t("owner.tenants.detail.tabs.emergency") },
]);

onMounted(async () => {
  try {
    tenant.value = await useTenants().get(route.params.id as string);
  } finally {
    loading.value = false;
  }
});

useHead({
  title: () => tenant.value?.name ?? t("owner.nav.tenants"),
});

const onSaved = (updated: Tenant) => {
  tenant.value = updated;
};

const confirmDelete = async () => {
  if (!tenant.value) return;
  deleting.value = true;
  try {
    await useTenants().remove(tenant.value.id);
    show(t("owner.tenants.deletedToast"), "success");
    router.push("/owner/tenants");
  } finally {
    deleting.value = false;
  }
};

const statusTone = (status: TenantStatus) => {
  switch (status) {
    case "invited":
      return "draft";
    case "active":
      return "active";
    case "notice_given":
      return "maintenance";
    case "moved_out":
      return "expired";
  }
};

const tabTriggerClass =
  "-mb-px border-b-2 border-transparent px-4 py-2 text-body text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus data-[state=active]:border-ink data-[state=active]:text-ink";
</script>

<template>
  <div>
    <div class="mb-6 flex items-center justify-between gap-2">
      <NuxtLink
        to="/owner/tenants"
        class="inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink"
      >
        <Icon name="ArrowLeft" :size="14" />
        {{ t("owner.tenants.detail.back") }}
      </NuxtLink>
      <!-- Mobile: delete pairs with the back link -->
      <Button
        v-if="tenant"
        variant="ghost"
        size="sm"
        class="sm:hidden"
        @click="showDeleteConfirm = true"
      >
        <Icon name="Trash2" :size="14" class="mr-1" />
        {{ t("owner.tenants.detail.delete") }}
      </Button>
    </div>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("common.loading") }}
      </p>
    </Card>

    <Card v-else-if="!tenant" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("owner.tenants.detail.notFound") }}
      </p>
    </Card>

    <template v-else>
      <header class="mb-6 sm:flex sm:items-end sm:justify-between sm:gap-4">
        <div class="sm:min-w-0">
          <Pill :tone="statusTone(tenant.status)" class="mb-3">
            {{ t(`owner.tenants.status.${tenant.status}`) }}
          </Pill>
          <h1 class="text-display-sub font-semibold tracking-snug text-ink">
            {{ tenant.name }}
          </h1>
          <p class="mt-2 text-caption text-ink-muted">
            {{ tenant.email }} · {{ tenant.phone }}
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
          {{ t("owner.tenants.detail.delete") }}
        </Button>
      </header>

      <Card padding="loose">
        <TabsRoot v-model="activeTab">
          <!-- Mobile: dropdown picker. Desktop: tab strip. -->
          <div class="mb-6 sm:hidden">
            <Select v-model="activeTab" :options="tabOptions" />
          </div>
          <TabsList class="mb-6 hidden gap-1 border-b border-line-passive sm:flex">
            <TabsTrigger value="identity" :class="tabTriggerClass">
              {{ t("owner.tenants.detail.tabs.identity") }}
            </TabsTrigger>
            <TabsTrigger value="personal" :class="tabTriggerClass">
              {{ t("owner.tenants.detail.tabs.personal") }}
            </TabsTrigger>
            <TabsTrigger value="emergency" :class="tabTriggerClass">
              {{ t("owner.tenants.detail.tabs.emergency") }}
            </TabsTrigger>
          </TabsList>

          <TabsContent value="identity" class="outline-none">
            <TenantIdentityForm :tenant="tenant" @saved="onSaved" />
          </TabsContent>
          <TabsContent value="personal" class="outline-none">
            <TenantPersonalForm :tenant="tenant" @saved="onSaved" />
          </TabsContent>
          <TabsContent value="emergency" class="outline-none">
            <TenantEmergencyContactForm
              :tenant="tenant"
              @saved="onSaved"
            />
          </TabsContent>
        </TabsRoot>
      </Card>

      <div v-if="documentsEnabled" class="mt-6">
        <Card padding="loose">
          <header class="mb-4">
            <h2 class="text-card-title font-semibold text-ink">
              {{ t("owner.tenants.detail.documents.title") }}
            </h2>
            <p class="mt-1 text-caption text-ink-muted">
              {{ t("owner.tenants.detail.documents.help") }}
            </p>
          </header>
          <EmptyState
            icon="FileText"
            :title="t('owner.tenants.detail.documents.placeholderTitle')"
            :description="t('owner.tenants.detail.documents.placeholderHelp')"
          />
        </Card>
      </div>

      <Modal
        v-model:open="showDeleteConfirm"
        :title="t('owner.tenants.deleteConfirm.title')"
        size="sm"
      >
        <p class="text-body text-ink">
          {{
            t("owner.tenants.deleteConfirm.body", { name: tenant.name })
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
            {{ t("owner.tenants.deleteConfirm.confirm") }}
          </Button>
        </template>
      </Modal>
    </template>
  </div>
</template>
