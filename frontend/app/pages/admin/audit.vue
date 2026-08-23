<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import Card from "~/components/ui/Card.vue";
import Input from "~/components/ui/Input.vue";
import Select from "~/components/ui/Select.vue";
import Button from "~/components/ui/Button.vue";
import EmptyState from "~/components/ui/EmptyState.vue";
import AuditTable from "~/components/admin/AuditTable.vue";
import { AUDIT_ACTIONS, type AuditAction, type AuditEntry, type AuditQuery, type Paginated } from "~/types/admin";
import { downloadCsvText } from "~/utils/csv";

definePageMeta({ layout: "admin" });
const { t } = useI18n();
useHead({ title: () => t("admin.nav.audit") });
const { can } = useAdminPermissions();

const action = ref<AuditAction | "all">("all");
const actorId = ref("");
const subjectId = ref("");
const from = ref("");
const to = ref("");
const page = ref(1);
const loading = ref(true);
const exporting = ref(false);
const result = ref<Paginated<AuditEntry>>({ data: [], meta: { page: 1, perPage: 25, total: 0, lastPage: 1 } });

const query = computed<AuditQuery>(() => ({
  action: action.value === "all" ? undefined : action.value,
  actorId: actorId.value || undefined,
  subjectId: subjectId.value || undefined,
  from: from.value || undefined,
  to: to.value || undefined,
  page: page.value,
}));

const load = async () => { loading.value = true; try { result.value = await useAdminAudit().list(query.value); } finally { loading.value = false; } };
onMounted(load);
watch([action, from, to], () => {
  if (page.value !== 1) page.value = 1; // watch(page) will load
  else load();
});
watch(page, load);

let debounce: ReturnType<typeof setTimeout> | null = null;
watch([actorId, subjectId], () => {
  if (debounce) clearTimeout(debounce);
  debounce = setTimeout(() => {
    if (page.value !== 1) page.value = 1; // watch(page) will load
    else load();
  }, 300);
});

const actionOptions = computed(() => [
  { value: "all", label: t("admin.common.all") },
  ...AUDIT_ACTIONS.map((a) => ({ value: a, label: t(`admin.audit.actions.${a}`) })),
]);

const exportCsv = async () => {
  exporting.value = true;
  try {
    const csv = await useAdminAudit().exportCsv({ ...query.value, page: undefined });
    downloadCsvText(`roofly-audit-${new Date().toISOString().slice(0, 10)}.csv`, csv);
  } catch {
    useToast().show(t("common.genericError"), "danger");
  } finally {
    exporting.value = false;
  }
};
</script>

<template>
  <div>
    <header class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h1 class="text-display-sub font-semibold tracking-snug">{{ t("admin.audit.title") }}</h1>
        <p class="mt-2 text-caption text-ink-muted">{{ can("audit.view") ? t("admin.audit.subtitleAll") : t("admin.audit.subtitleOwn") }}</p>
      </div>
      <Button v-if="can('audit.view')" variant="ghost" size="sm" class="self-start" :loading="exporting" @click="exportCsv">{{ t("admin.common.exportCsv") }}</Button>
    </header>

    <Card padding="compact" class="mb-4 sm:mb-6">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <Select v-model="action" :options="actionOptions" :label="t('admin.audit.filters.action')" />
        <Input v-if="can('audit.view')" v-model="actorId" :label="t('admin.audit.filters.actorId')" />
        <Input v-model="subjectId" :label="t('admin.audit.filters.subjectId')" />
        <Input v-model="from" type="date" :label="t('admin.audit.filters.from')" />
        <Input v-model="to" type="date" :label="t('admin.audit.filters.to')" />
      </div>
    </Card>

    <Card v-if="loading" padding="loose"><p class="text-center text-body text-ink-muted">{{ t("common.loading") }}</p></Card>
    <Card v-else-if="result.data.length === 0" padding="loose"><EmptyState icon="ScrollText" :title="t('admin.common.noResults')" :description="t('admin.common.noResultsHelp')" /></Card>
    <template v-else>
      <Card padding="compact"><AuditTable :entries="result.data" /></Card>
      <footer class="mt-4 flex items-center justify-between gap-3 text-caption text-ink-muted">
        <span>{{ t("admin.common.pageOf", { page: result.meta.page, lastPage: result.meta.lastPage, total: result.meta.total }) }}</span>
        <div class="flex gap-2">
          <Button variant="ghost" size="sm" :disabled="page <= 1" @click="page--">{{ t("common.back") }}</Button>
          <Button variant="ghost" size="sm" :disabled="page >= result.meta.lastPage" @click="page++">{{ t("common.next") }}</Button>
        </div>
      </footer>
    </template>
  </div>
</template>
