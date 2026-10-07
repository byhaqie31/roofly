<script setup lang="ts">
import { computed, h, onMounted, ref, watch } from "vue";
import { FlexRender, getCoreRowModel, useVueTable, type ColumnDef } from "@tanstack/vue-table";
import Card from "~/components/ui/Card.vue";
import Input from "~/components/ui/Input.vue";
import Button from "~/components/ui/Button.vue";
import Pill from "~/components/ui/Pill.vue";
import DataTableShell from "~/components/admin/DataTableShell.vue";
import LeadDrawer from "~/components/admin/LeadDrawer.vue";
import InviteLeadModal from "~/components/admin/InviteLeadModal.vue";
import { downloadCsvText } from "~/utils/csv";
import { formatAdminDate } from "~/utils/adminDate";
import type { AdminLead, LeadListQuery } from "~/types/analytics";
import type { Paginated } from "~/types/admin";

// Enquiries → Waitlist tab: every email left via the coming-soon "Notify me" form
// (POST /waitlist). Same leads endpoints as Analytics, with `source` pinned to
// "waitlist" so registrations never mix in — it is the list to follow up, not the
// funnel. Admins with broadcast.send can email a lead the sign-up invitation.
// The page (pages/admin/enquiries.vue) only mounts this with analytics.view.
const { can } = useAdminPermissions();
const { t } = useI18n();

const route = useRoute();
const router = useRouter();

const q = ref(String(route.query.q ?? ""));
const page = ref(Number(route.query.page ?? 1));

const loading = ref(true);
const result = ref<Paginated<AdminLead>>({ data: [], meta: { page: 1, perPage: 20, total: 0, lastPage: 1 } });

const query = computed<LeadListQuery>(() => ({
  q: q.value || undefined,
  source: "waitlist",
  page: page.value,
}));

const load = async () => {
  loading.value = true;
  try {
    result.value = await useAdminAnalytics().leads(query.value);
  } finally {
    loading.value = false;
  }
  syncRoute();
};

function syncRoute() {
  router.replace({
    query: Object.fromEntries(
      Object.entries({
        tab: route.query.tab ? String(route.query.tab) : undefined, // keep the page's tab
        q: q.value || undefined,
        page: page.value > 1 ? String(page.value) : undefined,
      }).filter(([, v]) => v !== undefined),
    ) as Record<string, string>,
  });
}

onMounted(load);

watch(page, load);

let debounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (debounce) clearTimeout(debounce);
  debounce = setTimeout(() => {
    if (page.value !== 1) page.value = 1; // watch(page) will load
    else load();
  }, 300);
});

const fmtDate = formatAdminDate;

// ── Drawer ─────────────────────────────────────────────────────────────
const drawerOpen = ref(false);
const selectedLeadId = ref<string | null>(null);
const openLead = (lead: AdminLead) => {
  selectedLeadId.value = lead.id;
  drawerOpen.value = true;
};

const goToOwner = (id: string, e: MouseEvent) => {
  e.stopPropagation();
  e.preventDefault();
  router.push(`/admin/owners/${id}`);
};

// ── Invite ─────────────────────────────────────────────────────────────
const canInvite = computed(() => can("broadcast.send"));
const inviteOpen = ref(false);
const inviteLead = ref<AdminLead | null>(null);
const openInvite = (lead: AdminLead, e?: Event) => {
  e?.stopPropagation(); // the row itself opens the drawer
  inviteLead.value = lead;
  inviteOpen.value = true;
};
const onInvited = (updated: AdminLead) => {
  result.value = { ...result.value, data: result.value.data.map((l) => (l.id === updated.id ? updated : l)) };
};

const statusCell = (lead: AdminLead) => {
  const ownerId = lead.convertedUserId;
  if (!ownerId && lead.invitedAt) return h(Pill, { tone: "pending" }, { default: () => t("admin.enquiries.status.invited") });
  if (!ownerId) return h(Pill, { tone: "neutral" }, { default: () => t("admin.enquiries.status.waiting") });
  return h("span", { class: "inline-flex items-center gap-2" }, [
    h(Pill, { tone: "active" }, { default: () => t("admin.enquiries.status.registered") }),
    h(
      "a",
      {
        href: `/admin/owners/${ownerId}`,
        class: "text-caption text-ink underline underline-offset-2",
        onClick: (e: MouseEvent) => goToOwner(ownerId, e),
        onKeydown: (e: KeyboardEvent) => e.stopPropagation(),
      },
      lead.convertedOwnerName ?? "",
    ),
  ]);
};

const inviteCell = (lead: AdminLead) => {
  if (lead.convertedUserId) return h("span", { class: "text-caption text-ink-faint" }, "—");
  return h("span", { class: "inline-flex flex-col items-start gap-1", onKeydown: (e: KeyboardEvent) => e.stopPropagation() }, [
    h(Button, { size: "sm", variant: lead.invitedAt ? "ghost" : "primary", onClick: (e: MouseEvent) => openInvite(lead, e) }, {
      default: () => (lead.invitedAt ? t("admin.enquiries.invite.resend") : t("admin.enquiries.invite.send")),
    }),
    lead.invitedAt
      ? h("span", { class: "text-micro text-ink-faint tabular-nums" }, t("admin.enquiries.invite.invitedOn", { date: fmtDate(lead.invitedAt) }))
      : null,
  ]);
};

const columns = computed<ColumnDef<AdminLead>[]>(() => [
  { id: "email", header: () => t("admin.enquiries.columns.email"), cell: (i) => h("span", { class: "text-body text-ink" }, i.row.original.email) },
  { id: "signedUp", header: () => t("admin.enquiries.columns.signedUp"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.firstSeenAt)) },
  { id: "lastSeen", header: () => t("admin.enquiries.columns.lastSeen"), cell: (i) => h("span", { class: "text-caption tabular-nums" }, fmtDate(i.row.original.lastSeenAt)) },
  { id: "status", header: () => t("admin.enquiries.columns.status"), cell: (i) => statusCell(i.row.original) },
  ...(canInvite.value ? [{ id: "invite", header: () => t("admin.enquiries.columns.actions"), cell: (i) => inviteCell(i.row.original) } as ColumnDef<AdminLead>] : []),
]);

const table = useVueTable({
  get data() { return result.value.data; },
  get columns() { return columns.value; },
  getCoreRowModel: getCoreRowModel(),
  manualPagination: true,
});

const exporting = ref(false);
const exportCsv = async () => {
  exporting.value = true;
  try {
    const csv = await useAdminAnalytics().exportCsv({ ...query.value, page: undefined });
    downloadCsvText(`roofly-enquiries-${new Date().toISOString().slice(0, 10)}.csv`, csv);
  } catch {
    useToast().show(t("common.genericError"), "danger");
  } finally {
    exporting.value = false;
  }
};
</script>

<template>
  <div>
    <Card padding="compact" class="mb-4 sm:mb-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <Input v-model="q" :placeholder="t('admin.enquiries.searchPlaceholder')" class="sm:max-w-md sm:flex-1" />
        <Button variant="ghost" size="sm" class="self-start sm:self-auto" :loading="exporting" @click="exportCsv">{{ t("admin.common.exportCsv") }}</Button>
      </div>
    </Card>

    <DataTableShell
      :loading="loading"
      :empty="result.data.length === 0"
      :empty-title="t('admin.enquiries.empty')"
      :empty-description="t('admin.enquiries.emptyHelp')"
      :page="result.meta.page"
      :last-page="result.meta.lastPage"
      :total="result.meta.total"
      @update:page="page = $event"
    >
      <template #table>
        <table class="w-full text-left">
          <thead>
            <tr v-for="hg in table.getHeaderGroups()" :key="hg.id" class="border-b border-line-passive">
              <th v-for="hd in hg.headers" :key="hd.id" class="px-3 py-2 text-micro font-medium uppercase tracking-wider text-ink-muted">
                <FlexRender :render="hd.column.columnDef.header" :props="hd.getContext()" />
              </th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="row in table.getRowModel().rows"
              :key="row.id"
              tabindex="0"
              role="link"
              class="border-b border-line-passive last:border-0 cursor-pointer outline-none hover:bg-surface-hover focus-visible:shadow-focus"
              @click="openLead(row.original)"
              @keydown.enter.prevent="openLead(row.original)"
            >
              <td v-for="cell in row.getVisibleCells()" :key="cell.id" class="px-3 py-3 align-top">
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </td>
            </tr>
          </tbody>
        </table>
      </template>
      <template #cards>
        <Card v-for="lead in result.data" :key="lead.id" padding="compact">
          <button type="button" class="block w-full text-left rounded-lg outline-none focus-visible:shadow-focus" @click="openLead(lead)">
            <div class="flex items-center gap-2">
              <Pill :tone="lead.convertedUserId ? 'active' : lead.invitedAt ? 'pending' : 'neutral'">
                {{ lead.convertedUserId ? t("admin.enquiries.status.registered") : lead.invitedAt ? t("admin.enquiries.status.invited") : t("admin.enquiries.status.waiting") }}
              </Pill>
              <span class="text-micro text-ink-faint">{{ t("admin.enquiries.columns.signedUp") }} {{ fmtDate(lead.firstSeenAt) }}</span>
            </div>
            <p class="mt-1 text-body font-medium text-ink">{{ lead.email }}</p>
            <p class="text-caption text-ink-muted">
              {{ t("admin.enquiries.columns.lastSeen") }} {{ fmtDate(lead.lastSeenAt) }}
              <template v-if="lead.convertedOwnerName"> · {{ lead.convertedOwnerName }}</template>
            </p>
          </button>
          <div v-if="canInvite && !lead.convertedUserId" class="mt-3 flex items-center gap-3">
            <Button size="sm" :variant="lead.invitedAt ? 'ghost' : 'primary'" @click="openInvite(lead)">
              {{ lead.invitedAt ? t("admin.enquiries.invite.resend") : t("admin.enquiries.invite.send") }}
            </Button>
            <span v-if="lead.invitedAt" class="text-micro text-ink-faint tabular-nums">
              {{ t("admin.enquiries.invite.invitedOn", { date: fmtDate(lead.invitedAt) }) }}
            </span>
          </div>
        </Card>
      </template>
    </DataTableShell>

    <LeadDrawer v-model:open="drawerOpen" :lead-id="selectedLeadId" />
    <InviteLeadModal v-model:open="inviteOpen" :lead="inviteLead" @sent="onInvited" />
  </div>
</template>
