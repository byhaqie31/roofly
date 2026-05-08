<script setup lang="ts">
import { onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import Button from "~/components/ui/Button.vue";
import TenantCard from "~/components/owner/TenantCard.vue";
import TenantFormModal from "~/components/owner/TenantFormModal.vue";
import type { Tenant } from "~/types/tenant";

definePageMeta({ layout: "owner" });
const { t } = useI18n();
useHead({ title: () => t("owner.nav.tenants") });

const tenants = ref<Tenant[]>([]);
const loading = ref(true);
const showModal = ref(false);
const editingTenant = ref<Tenant | null>(null);

const refresh = async () => {
  tenants.value = await useTenants().list();
};

onMounted(async () => {
  try {
    await refresh();
  } finally {
    loading.value = false;
  }
});

const onInvite = () => {
  editingTenant.value = null;
  showModal.value = true;
};

const onEdit = (tenant: Tenant) => {
  editingTenant.value = tenant;
  showModal.value = true;
};

const onSaved = async () => {
  await refresh();
};

const onDeleted = async () => {
  await refresh();
};
</script>

<template>
  <div>
    <header class="mb-8 flex items-end justify-between">
      <h1 class="text-display-sub font-semibold tracking-snug">
        {{ t("owner.nav.tenants") }}
      </h1>
      <Button variant="primary" @click="onInvite">
        + {{ t("owner.tenants.inviteCta") }}
      </Button>
    </header>

    <Card v-if="loading" padding="loose">
      <p class="text-center text-body text-ink-muted">
        {{ t("common.loading") }}
      </p>
    </Card>

    <Card v-else-if="tenants.length === 0" padding="loose">
      <EmptyState
        icon="Users"
        :title="t('owner.tenants.emptyTitle')"
        :description="t('owner.tenants.emptyDescription')"
      />
    </Card>

    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <TenantCard
        v-for="ten in tenants"
        :key="ten.id"
        :tenant="ten"
        @click="onEdit(ten)"
      />
    </div>

    <TenantFormModal
      v-model:open="showModal"
      :tenant="editingTenant"
      @saved="onSaved"
      @deleted="onDeleted"
    />
  </div>
</template>
