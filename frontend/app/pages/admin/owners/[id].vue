<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { TabsRoot, TabsList, TabsTrigger, TabsContent } from "reka-ui";
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";
import Select from "~/components/ui/Select.vue";
import Pill from "~/components/ui/Pill.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import OwnerStatusPill from "~/components/admin/OwnerStatusPill.vue";
import WarnOwnerModal from "~/components/admin/WarnOwnerModal.vue";
import SuspendOwnerModal from "~/components/admin/SuspendOwnerModal.vue";
import AuditTable from "~/components/admin/AuditTable.vue";
import type { AdminOwner, AdminPropertySummary, AdminTenant, AuditEntry, TenantStatus } from "~/types/admin";

import NoAccess from "~/components/admin/NoAccess.vue";

definePageMeta({ layout: "admin" });
const { t } = useI18n();
const route = useRoute();
const { can } = useAdminPermissions();
const { show } = useToast();

const id = route.params.id as string;
const owner = ref<AdminOwner | null>(null);
const loading = ref(true);
const activeTab = ref("overview");
const properties = ref<AdminPropertySummary[] | null>(null);
const tenants = ref<AdminTenant[] | null>(null);
const history = ref<AuditEntry[] | null>(null);
const showWarn = ref(false);
const showSuspend = ref(false);

useHead({ title: () => owner.value?.name ?? t("admin.nav.owners") });

onMounted(async () => {
  if (!can("owners.view")) return;
  try { owner.value = await useAdminOwners().get(id); } finally { loading.value = false; }
});

const loadHistory = async () => { history.value = await useAdminOwners().history(id); };

// Lazy-load each tab once.
watch(activeTab, async (tab) => {
  if (tab === "properties" && properties.value === null) properties.value = await useAdminOwners().properties(id);
  if (tab === "tenants" && tenants.value === null) tenants.value = await useAdminOwners().tenants(id);
  if (tab === "history" && history.value === null) await loadHistory();
});

const tabOptions = computed(() => [
  { value: "overview", label: t("admin.owners.detail.tabs.overview") },
  { value: "properties", label: t("admin.owners.detail.tabs.properties") },
  { value: "tenants", label: t("admin.owners.detail.tabs.tenants") },
  { value: "history", label: t("admin.owners.detail.tabs.history") },
]);
const tabTriggerClass = "-mb-px border-b-2 border-transparent px-4 py-2 text-body text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus data-[state=active]:border-admin data-[state=active]:text-ink";

const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString("en-MY", { day: "2-digit", month: "short", year: "numeric" }) : t("admin.common.never"));
const usagePct = computed(() => !owner.value || owner.value.unitsCap === null ? 0 : Math.min(100, Math.round((owner.value.unitsUsed / owner.value.unitsCap) * 100)));
const overCap = computed(() => !!owner.value && owner.value.unitsCap !== null && owner.value.unitsUsed > owner.value.unitsCap);

const tenantTone = (s: TenantStatus) => (s === "invited" ? "draft" : s === "active" ? "active" : s === "notice_given" ? "maintenance" : "expired");

const onStatusChanged = (updated: AdminOwner) => {
  owner.value = updated;
  if (activeTab.value === "history") loadHistory();
  else history.value = null;
};
const onWarned = () => {
  if (activeTab.value === "history") loadHistory();
  else history.value = null;
};

const resendingId = ref<string | null>(null);
const resend = async (tenant: AdminTenant) => {
  resendingId.value = tenant.id;
  try {
    await useAdminTenants().resendInvite(tenant.id);
    show(t("admin.tenants.resendToast"), "success");
    tenants.value = await useAdminOwners().tenants(id);
  } catch { show(t("common.genericError"), "danger"); } finally { resendingId.value = null; }
};

const countKeys = ["properties", "units", "unitsOccupied", "tenants", "agreementsActive", "agreementsExpiring30d", "invoicesOverdue", "ticketsOpen"] as const;
</script>

<template>
  <NoAccess v-if="!can('owners.view')" permission="owners.view" />
  <div v-else>
    <NuxtLink to="/admin/owners" class="mb-6 inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink">
      <Icon name="ArrowLeft" :size="14" />{{ t("admin.common.back") }}
    </NuxtLink>

    <Card v-if="loading" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
    <Card v-else-if="!owner" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("admin.common.notFound") }}</p></Card>

    <template v-else>
      <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-display-sub font-semibold tracking-snug">{{ owner.name }}</h1>
            <OwnerStatusPill :status="owner.status" />
          </div>
          <p class="mt-1 text-caption text-ink-muted">{{ owner.businessName ? `${owner.businessName} · ` : "" }}{{ owner.email }}{{ owner.phone ? ` · ${owner.phone}` : "" }}</p>
        </div>
        <div class="flex gap-2 self-start">
          <Button v-if="can('owners.warn')" variant="ghost" size="sm" @click="showWarn = true">
            <Icon name="BellRing" :size="14" class="mr-1" />{{ t("admin.owners.detail.actions.warn") }}
          </Button>
          <Button v-if="can('owners.suspend')" :variant="owner.status === 'suspended' ? 'primary' : 'accent'" size="sm" @click="showSuspend = true">
            {{ owner.status === "suspended" ? t("admin.owners.detail.actions.unsuspend") : t("admin.owners.detail.actions.suspend") }}
          </Button>
        </div>
      </header>

      <Card v-if="owner.status === 'suspended'" padding="compact" class="mb-6 border-status-terminated">
        <p class="text-caption text-ink"><span class="font-medium">{{ t("admin.owners.detail.suspendedSince", { date: fmtDate(owner.suspendedAt) }) }}</span> — {{ owner.suspensionReason }}</p>
      </Card>

      <TabsRoot v-model="activeTab">
        <div class="sm:hidden mb-4"><Select v-model="activeTab" :options="tabOptions" /></div>
        <TabsList class="hidden sm:flex mb-6 border-b border-line-passive">
          <TabsTrigger v-for="tab in tabOptions" :key="tab.value" :value="tab.value" :class="tabTriggerClass">{{ tab.label }}</TabsTrigger>
        </TabsList>

        <TabsContent value="overview">
          <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
            <Card padding="standard">
              <p class="text-caption text-ink-muted">{{ t("admin.owners.detail.plan") }}</p>
              <p class="mt-2 text-card-title font-semibold">{{ t(`admin.plan.${owner.planTier}`) }}</p>
              <p class="mt-3 text-caption text-ink-strong tabular-nums">{{ owner.unitsUsed }} / {{ owner.unitsCap ?? t("admin.common.unlimited") }} {{ t("admin.owners.detail.unitsUsed") }}</p>
              <div v-if="owner.unitsCap !== null" class="mt-2 h-1.5 w-full overflow-hidden rounded-pill bg-line-passive">
                <div :class="['h-full', overCap ? 'bg-status-overdue' : 'bg-admin']" :style="{ width: `${usagePct}%` }" />
              </div>
              <p v-if="overCap" class="mt-2 text-micro text-status-overdue">{{ t("admin.owners.detail.overCap") }}</p>
            </Card>
            <Card padding="standard" class="lg:col-span-2">
              <p class="text-caption text-ink-muted">{{ t("admin.owners.detail.counts") }}</p>
              <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div v-for="k in countKeys" :key="k">
                  <dt class="text-micro text-ink-faint">{{ t(`admin.owners.detail.countLabels.${k}`) }}</dt>
                  <dd class="text-body font-semibold tabular-nums" :class="k === 'invoicesOverdue' && owner.counts[k] > 0 ? 'text-status-overdue' : ''">{{ owner.counts[k] }}</dd>
                </div>
              </dl>
              <p class="mt-4 text-micro text-ink-faint">{{ t("admin.common.signedUp") }} {{ fmtDate(owner.createdAt) }} · {{ t("admin.common.lastActive") }} {{ fmtDate(owner.lastActiveAt) }}</p>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="properties">
          <Card v-if="properties === null" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
          <Card v-else-if="properties.length === 0" padding="loose"><EmptyState icon="Building2" :title="t('admin.owners.detail.noProperties')" /></Card>
          <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Card v-for="p in properties" :key="p.id" padding="standard">
              <div class="flex items-center gap-2"><Pill tone="neutral">{{ p.type ?? "—" }}</Pill><span class="text-micro text-ink-faint tabular-nums">{{ p.unitsOccupied }} / {{ p.unitsTotal }} {{ t("admin.owners.detail.occupied") }}</span></div>
              <p class="mt-1 text-body font-medium text-ink">{{ p.name }}</p>
              <p class="text-caption text-ink-muted">{{ [p.address.line, p.address.postcode, p.address.city, p.address.state].filter(Boolean).join(", ") }}</p>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="tenants">
          <Card v-if="tenants === null" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
          <Card v-else-if="tenants.length === 0" padding="loose"><EmptyState icon="Users" :title="t('admin.owners.detail.noTenants')" /></Card>
          <Card v-else padding="compact">
            <ul class="divide-y divide-line-passive">
              <li v-for="tn in tenants" :key="tn.id" class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                <NuxtLink :to="`/admin/tenants/${tn.id}`" class="min-w-0 flex-1">
                  <div class="flex items-center gap-2"><Pill :tone="tenantTone(tn.status)">{{ t(`admin.status.tenant.${tn.status}`) }}</Pill><span class="text-micro text-ink-faint">{{ tn.propertyName ?? "—" }} · {{ tn.unitLabel ?? "—" }}</span></div>
                  <p class="mt-1 text-body font-medium text-ink">{{ tn.name }}</p>
                  <p class="text-caption text-ink-muted">{{ tn.email }}</p>
                </NuxtLink>
                <Button v-if="tn.status === 'invited' && can('tenants.view')" variant="ghost" size="sm" class="self-start" :loading="resendingId === tn.id" @click="resend(tn)">{{ t("admin.tenants.resendInvite") }}</Button>
              </li>
            </ul>
          </Card>
        </TabsContent>

        <TabsContent value="history">
          <Card v-if="history === null" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
          <Card v-else padding="compact"><AuditTable :entries="history" /></Card>
        </TabsContent>
      </TabsRoot>

      <WarnOwnerModal v-model:open="showWarn" :owner="owner" @sent="onWarned" />
      <SuspendOwnerModal v-model:open="showSuspend" :owner="owner" :mode="owner.status === 'suspended' ? 'unsuspend' : 'suspend'" @done="onStatusChanged" />
    </template>
  </div>
</template>
