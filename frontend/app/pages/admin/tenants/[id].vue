<script setup lang="ts">
import { onMounted, ref } from "vue";
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import Icon from "~/components/ui/Icon.vue";
import Pill from "~/components/ui/Pill.vue";
import AuditTable from "~/components/admin/AuditTable.vue";
import type { AdminTenant, AuditEntry, TenantStatus } from "~/types/admin";

import NoAccess from "~/components/admin/NoAccess.vue";

definePageMeta({ layout: "admin" });
const { can } = useAdminPermissions();
const { t } = useI18n();
const route = useRoute();
const { show } = useToast();

const id = route.params.id as string;
const tenant = ref<AdminTenant | null>(null);
const history = ref<AuditEntry[]>([]);
const loading = ref(true);
const resending = ref(false);

useHead({ title: () => tenant.value?.name ?? t("admin.nav.tenants") });

const load = async () => {
  tenant.value = await useAdminTenants().get(id);
  history.value = (await useAdminAudit().list({ subjectType: "user", subjectId: id, perPage: 50 })).data;
};
onMounted(async () => { if (!can("tenants.view")) return; try { await load(); } finally { loading.value = false; } });

const tone = (s: TenantStatus) => (s === "invited" ? "draft" : s === "active" ? "active" : s === "notice_given" ? "maintenance" : "expired");
const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString("en-MY", { day: "2-digit", month: "short", year: "numeric" }) : "—");

const resend = async () => {
  resending.value = true;
  try { await useAdminTenants().resendInvite(id); show(t("admin.tenants.resendToast"), "success"); await load(); }
  catch { show(t("common.genericError"), "danger"); }
  finally { resending.value = false; }
};
</script>

<template>
  <NoAccess v-if="!can('tenants.view')" permission="tenants.view" />
  <div v-else>
    <NuxtLink to="/admin/tenants" class="mb-6 inline-flex items-center gap-1 text-caption text-ink-muted transition hover:text-ink"><Icon name="ArrowLeft" :size="14" />{{ t("admin.common.back") }}</NuxtLink>
    <Card v-if="loading" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
    <Card v-else-if="!tenant" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("admin.common.notFound") }}</p></Card>
    <template v-else>
      <header class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div class="flex items-center gap-2"><h1 class="text-display-sub font-semibold tracking-snug">{{ tenant.name }}</h1><Pill :tone="tone(tenant.status)">{{ t(`admin.status.tenant.${tenant.status}`) }}</Pill></div>
          <p class="mt-1 text-caption text-ink-muted">{{ tenant.email }}{{ tenant.phone ? ` · ${tenant.phone}` : "" }}</p>
        </div>
        <Button v-if="tenant.status === 'invited'" variant="ghost" size="sm" class="self-start" :loading="resending" @click="resend">{{ t("admin.tenants.resendInvite") }}</Button>
      </header>

      <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-3">
        <Card padding="standard" class="lg:col-span-1">
          <p class="text-caption text-ink-muted">{{ t("admin.tenants.detail.placement") }}</p>
          <dl class="mt-3 space-y-3 text-caption">
            <div><dt class="text-micro text-ink-faint">{{ t("admin.tenants.columns.owner") }}</dt><dd><NuxtLink v-if="tenant.ownerId" :to="`/admin/owners/${tenant.ownerId}`" class="text-ink underline underline-offset-2">{{ tenant.ownerName }}</NuxtLink><span v-else>—</span></dd></div>
            <div><dt class="text-micro text-ink-faint">{{ t("admin.tenants.columns.propertyUnit") }}</dt><dd class="text-ink">{{ tenant.propertyName ?? "—" }} · {{ tenant.unitLabel ?? "—" }}</dd></div>
            <div><dt class="text-micro text-ink-faint">{{ t("admin.tenants.columns.invited") }}</dt><dd class="text-ink tabular-nums">{{ fmtDate(tenant.invitedAt) }}</dd></div>
            <div><dt class="text-micro text-ink-faint">{{ t("admin.tenants.columns.accepted") }}</dt><dd class="text-ink tabular-nums">{{ fmtDate(tenant.acceptedAt) }}</dd></div>
          </dl>
          <p class="mt-4 text-micro text-ink-faint">{{ t("admin.tenants.detail.privacy") }}</p>
        </Card>
        <Card padding="compact" class="lg:col-span-2">
          <h2 class="px-2 pt-2 text-card-title font-semibold text-ink">{{ t("admin.tenants.detail.history") }}</h2>
          <p v-if="history.length === 0" class="p-4 text-body text-ink-muted">{{ t("admin.tenants.detail.noHistory") }}</p>
          <AuditTable v-else :entries="history" />
        </Card>
      </div>
    </template>
  </div>
</template>
