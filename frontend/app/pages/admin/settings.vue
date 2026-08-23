<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { TabsRoot, TabsList, TabsTrigger, TabsContent } from "reka-ui";
import Card from "~/components/ui/Card.vue";
import Button from "~/components/ui/Button.vue";
import Pill from "~/components/ui/Pill.vue";
import Select from "~/components/ui/Select.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import AdminFormModal from "~/components/admin/AdminFormModal.vue";
import type { AdminUser, AdminUserStatus, PermissionCatalogue } from "~/types/admin";

definePageMeta({ layout: "admin" });
const { t } = useI18n();
useHead({ title: () => t("admin.nav.settings") });
const { can } = useAdminPermissions();
const { show } = useToast();
const auth = useAuthStore();

const activeTab = ref("admins");
const tabOptions = computed(() => [{ value: "admins", label: t("admin.settings.tabs.admins") }]);
const tabTriggerClass = "-mb-px border-b-2 border-transparent px-4 py-2 text-body text-ink-muted outline-none transition hover:text-ink focus-visible:shadow-focus data-[state=active]:border-admin data-[state=active]:text-ink";

const admins = ref<AdminUser[]>([]);
const catalogue = ref<PermissionCatalogue | null>(null);
const loading = ref(true);
const showForm = ref(false);
const editing = ref<AdminUser | null>(null);
const busyId = ref<string | null>(null);

const load = async () => { admins.value = await useAdminAdmins().list(); };
onMounted(async () => {
  if (!can("admins.manage")) { loading.value = false; return; }
  try { [catalogue.value] = await Promise.all([useAdminAdmins().permissions(), load()]); } finally { loading.value = false; }
});

const openCreate = () => { editing.value = null; showForm.value = true; };
const openEdit = (a: AdminUser) => { editing.value = a; showForm.value = true; };
const onSaved = async () => { await load(); };

const toggleDisabled = async (a: AdminUser) => {
  busyId.value = a.id;
  try { await useAdminAdmins().update(a.id, { disabled: a.status !== "disabled" }); await load(); }
  catch (e) { show((e as { data?: { message?: string } })?.data?.message ?? (e as Error)?.message ?? t("common.genericError"), "danger"); }
  finally { busyId.value = null; }
};
const resend = async (a: AdminUser) => {
  busyId.value = a.id;
  try { await useAdminAdmins().resendInvite(a.id); show(t("admin.settings.admins.resentToast"), "success"); }
  catch { show(t("common.genericError"), "danger"); }
  finally { busyId.value = null; }
};

const tone = (s: AdminUserStatus) => (s === "active" ? "active" : s === "invited" ? "draft" : "expired");
const fmtDate = (iso: string | null) => (iso ? new Date(iso).toLocaleDateString("en-MY", { day: "2-digit", month: "short", year: "numeric" }) : t("admin.common.never"));
</script>

<template>
  <div>
    <header class="mb-6 sm:mb-8">
      <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.settings.title") }}</h1>
      <p class="mt-2 text-caption text-ink-muted">{{ t("admin.settings.subtitle") }}</p>
    </header>

    <Card v-if="!can('admins.manage')" padding="loose">
      <EmptyState icon="Lock" :title="t('admin.settings.noAccess')" :description="t('admin.settings.noAccessHelp')" />
    </Card>

    <TabsRoot v-else v-model="activeTab">
      <div class="sm:hidden mb-4"><Select v-model="activeTab" :options="tabOptions" /></div>
      <TabsList class="hidden sm:flex mb-6 border-b border-line-passive">
        <TabsTrigger v-for="tab in tabOptions" :key="tab.value" :value="tab.value" :class="tabTriggerClass">{{ tab.label }}</TabsTrigger>
      </TabsList>

      <TabsContent value="admins">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <p class="text-caption text-ink-muted">{{ t("admin.settings.admins.help") }}</p>
          <Button variant="primary" size="sm" class="self-start" @click="openCreate">{{ t("admin.settings.admins.create") }}</Button>
        </div>

        <Card v-if="loading" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
        <Card v-else padding="compact">
          <ul class="divide-y divide-line-passive">
            <li v-for="a in admins" :key="a.id" class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <Pill :tone="tone(a.status)">{{ t(`admin.status.admin.${a.status}`) }}</Pill>
                  <span class="text-micro text-ink-faint">{{ a.isSuperAdmin ? t("admin.settings.admins.superAdmin") : t("admin.settings.admins.permissionCount", { n: a.permissions.length }) }} · {{ t("admin.common.lastActive") }} {{ fmtDate(a.lastActiveAt) }}</span>
                </div>
                <p class="mt-1 text-body font-medium text-ink">{{ a.name }}<span v-if="a.id === auth.user?.id" class="ml-2 text-micro font-normal text-ink-faint">{{ t("admin.settings.admins.you") }}</span></p>
                <p class="text-caption text-ink-muted">{{ a.email }}</p>
              </div>
              <div class="flex flex-wrap gap-2 self-start">
                <Button variant="ghost" size="sm" @click="openEdit(a)">{{ t("admin.settings.admins.edit") }}</Button>
                <Button v-if="a.status === 'invited'" variant="ghost" size="sm" :loading="busyId === a.id" @click="resend(a)">{{ t("admin.settings.admins.resend") }}</Button>
                <Button v-if="a.id !== auth.user?.id" variant="ghost" size="sm" :loading="busyId === a.id" @click="toggleDisabled(a)">
                  {{ a.status === "disabled" ? t("admin.settings.admins.enable") : t("admin.settings.admins.disable") }}
                </Button>
              </div>
            </li>
          </ul>
        </Card>
      </TabsContent>
    </TabsRoot>

    <AdminFormModal v-if="catalogue" v-model:open="showForm" :catalogue="catalogue" :editing="editing" @saved="onSaved" />
  </div>
</template>
